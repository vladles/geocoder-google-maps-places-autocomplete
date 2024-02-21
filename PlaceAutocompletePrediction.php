<?php

namespace App\Services\GoogleMapsPlacesAutocomplete;

class PlaceAutocompletePrediction
{
    private string $description;

    private string $place_id;
    private array $types;


    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getPlaceId(): string
    {
        return $this->place_id;
    }

    public function setPlaceId(string $place_id): void
    {
        $this->place_id = $place_id;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function setTypes(array $types): void
    {
        $this->types = $types;
    }

}
