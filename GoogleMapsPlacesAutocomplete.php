<?php

namespace App\Services\GoogleMapsPlacesAutocomplete;

use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\QuotaExceeded;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Model\AddressBuilder;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\GoogleMapsPlaces\Model\GooglePlace;
use Geocoder\Provider\GoogleMapsPlaces\Model\OpeningHours;
use Geocoder\Provider\GoogleMapsPlaces\Model\Photo;
use Geocoder\Provider\GoogleMapsPlaces\Model\PlusCode;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\Query;
use Geocoder\Query\ReverseQuery;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientInterface;
use stdClass;

final class GoogleMapsPlacesAutocomplete extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const ENDPOINT_URL_SSL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    /**
     * @var string
     */
//    const DEFAULT_FIELDS = 'formatted_address,geometry,icon,name,permanently_closed,photos,place_id,plus_code,types';

    /**
     * @var string|null
     */
    private $apiKey;

    private $region;
    private $language;


    /**
     * @param  ClientInterface  $client  An HTTP adapter
     * @param  string           $apiKey  Google Maps Places API Key
     * @param  string           $region  Google Maps Places API Key
     */
    public function __construct(ClientInterface $client, string $apiKey, string $region = null, string $language = 'en')
    {
        parent::__construct($client);

        $this->apiKey = $apiKey;
        $this->region = $region;
        $this->language = $language;
    }

    /**
     * @param  GeocodeQuery  $query
     *
     * @return Collection
     *
     * @throws UnsupportedOperation
     * @throws InvalidArgument
     */
    public function geocodeQuery(GeocodeQuery $query): AddressCollection
    {
        return $this->fetchUrl($this->buildQuery($query));
    }

    /**
     * @param  ReverseQuery  $query
     *
     * @return Collection
     *
     * @throws InvalidArgument
     */
    public function reverseQuery(ReverseQuery $query): AddressCollection
    {
        throw new InvalidArgument('Reverse Query not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'google_maps_places_autocomplete';
    }

    /**
     * Build query for the find place API.
     *
     * @param  GeocodeQuery  $geocodeQuery
     *
     * @return array
     */
    private function buildQuery(GeocodeQuery $geocodeQuery): array
    {
        $query = [
            'input' => $geocodeQuery->getText(),
            'types' => 'geocode' // address?
        ];

        if (null !== $this->getLocale()) {
            $query['language'] = $this->getLocale();
        }

        if (null !== $this->getRegion()) {
            $query['region'] = $this->getRegion();
        }

        if (null !== $geocodeQuery->getData('radius')) {
            $query['radius'] = $geocodeQuery->getData('radius');
        }

        // If query has bounds, set location bias to those bounds
        if (null !== $bounds = $geocodeQuery->getBounds()) {
            $query['locationbias'] = sprintf(
                'rectangle:%s,%s|%s,%s',
                $bounds->getSouth(),
                $bounds->getWest(),
                $bounds->getNorth(),
                $bounds->getEast()
            );
        }

        if (true === $geocodeQuery->getData('location')) {
            $query['location'] = $geocodeQuery->getData('location');
            $query['strictbounds'] = true;
        }

        if (null !== $geocodeQuery->getData('sessiontoken')) {
            $query['sessiontoken'] = $geocodeQuery->getData('sessiontoken');
        }
        return $query;
    }


    private function fetchUrl(array $query): AddressCollection
    {
        $query['key'] = $this->apiKey;

        $url = sprintf('%s?%s', self::ENDPOINT_URL_SSL, http_build_query($query));

        $content = $this->getUrlContents($url);
        $json = $this->validateResponse($url, $content);

        $results = [];
        if (empty($json->predictions) || 'OK' !== $json->status) {
            return new AddressCollection([]);
        }

        $apiResults = $json->predictions;
        foreach ($apiResults as $result) {
            $place = new PlaceAutocompletePrediction();
            $place->setDescription($result->description);
            $place->setPlaceId($result->place_id);
            $place->setTypes($result->types);

            $results[] = $place;
        }

        return new AddressCollection($results);
    }

    /**
     * Decode the response content and validate it to make sure it does not have any errors.
     *
     * @param  string  $url
     * @param  string  $content
     *
     * @return \StdClass
     *
     * @throws InvalidCredentials
     * @throws InvalidServerResponse
     * @throws QuotaExceeded
     */
    private function validateResponse(string $url, $content): StdClass
    {
        $json = json_decode($content);

        // API error
        if (!isset($json)) {
            throw InvalidServerResponse::create($url);
        }

        if ('INVALID_REQUEST' === $json->status) {
            throw new InvalidArgument(sprintf('Invalid Request %s', $url));
        }

        if ('REQUEST_DENIED' === $json->status && 'The provided API key is invalid.' === $json->error_message) {
            throw new InvalidCredentials(sprintf('API key is invalid %s', $url));
        }

        if ('REQUEST_DENIED' === $json->status) {
            throw new InvalidServerResponse(sprintf('API access denied. Request: %s - Message: %s', $url, $json->error_message));
        }

        if ('OVER_QUERY_LIMIT' === $json->status) {
            throw new QuotaExceeded(sprintf('Daily quota exceeded %s', $url));
        }

        return $json;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getLocale(): ?string
    {
        return $this->language;
    }
}
