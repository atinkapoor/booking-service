<?php

namespace App\Observers;

use App\Model\Gym;
use App\Utilities\GoogleMaps;
use Illuminate\Support\Facades\Storage;

class GymObserver
{
    /**
     * Handle the gym "created" event.
     *
     * @param  \App\Model\Gym $gym
     * @return void
     */
    public function created(Gym $gym)
    {
        $this->setGeocode($gym);
    }

    /**
     * Handle the gym "updated" event.
     *
     * @param  \App\Model\Gym $gym
     * @return void
     */
    public function updated(Gym $gym)
    {
        $this->setGeocode($gym);
    }

    /**
     * Handle the gym "deleted" event.
     *
     * @param  \App\Model\Gym $gym
     * @return void
     */
    public function deleted(Gym $gym)
    {
        $gym->equipment()->sync([]);
        $gym->feature()->sync([]);
    }

    /**
     * Handle the gym "restored" event.
     *
     * @param  \App\Model\Gym $gym
     * @return void
     */
    public function restored(Gym $gym)
    {
        //
    }

    /**
     * Handle the gym "force deleted" event.
     *
     * @param  \App\Model\Gym $gym
     * @return void
     */
    public function forceDeleted(Gym $gym)
    {
        //
    }

    private function setGeocode($gym)
    {
        if ( $gym->whole_address != $gym->full_address ) {
            $geocode = GoogleMaps::geocodeAddress($gym->address_property_number . ' ' . $gym->address_street_name . ' ' . $gym->address_neighborhood, $gym->address_town, $gym->address_county_town, $gym->address_postcode);
            $gym->latitude = $geocode['lat'];
            $gym->longitude = $geocode['lng'];
            $gym->whole_address = $gym->full_address;

            //save static map image
            $map_image_content = GoogleMaps::staticmapAddress($geocode['lat'], $geocode['lng'], $gym->address_property_number . ' ' . $gym->address_street_name . ' ' . $gym->address_neighborhood, $gym->address_town, $gym->address_county_town, $gym->address_postcode);
            $filename = time() . '_' . "map.png";
            Storage::disk('gym_map_images')->put($filename, $map_image_content);
            $gym->map_image = $filename;

            $gym->save();
        }
    }
}
