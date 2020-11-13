<?php

namespace App\Model;

use App\Utilities\GoogleMaps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Gym extends Model
{
    use Geographical;
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'updated_at',
    ];
    protected static $kilometers = true;
    protected $appends = ['full_address', 'image_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'address_property_number', 'address_street_name', 'address_neighborhood', 'address_town', 'address_county_town', 'address_postcode', 'description', 'latitude', 'longitude', 'active', 'area_id','special_instructions', 'map_image',
    ];

    public function equipment()
    {
        return $this->belongsToMany(Equipment::class)->withPivot('out_of_order');
    }

    public function feature()
    {
        return $this->belongsToMany(Feature::class)->withPivot('out_of_order');
    }

    public function gym_images()
    {
        return $this->hasMany(GymImage::class);
    }

    public function gym_open_timings()
    {
        return $this->hasMany(GymOpenTiming::class)->with(['Ranges', 'open_time_slot', 'close_time_slot']);
    }

    public function gym_additionals()
    {
        return $this->hasMany(GymAdditional::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function areas()
    {
        return $this->belongsTo(Area::class);
    }

    public function getFullAddressAttribute()
    {
        return $this->address_property_number . ' - '
            . $this->address_street_name . ', '
            . $this->address_neighborhood . ', '
            . $this->address_town . ', '
            . $this->address_county_town . ', '
            . $this->address_postcode;
    }

    public function getImageUrlAttribute()
    {
        return env('WEB_URL') . env('GYM_MAP_IMAGES_FOLDER_PATH');
    }

    public function delete()
    {
        $gymImagesCollections = $this->gym_images()->get();
        foreach ($gymImagesCollections as $gymImagesCollection) {
            Storage::disk('gym_images')->delete($gymImagesCollection->image);
        }
        parent::delete();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public static function saveRelationalData(Request $request, $id = '')
    {
        $saveModel = self::getGymModel($request, $id);
        $saveModel->equipment()->sync($request->get('equipment'));
        $saveModel->feature()->sync($request->get('features'));
        $gymAddtionalDatas = self::gymAddtionalData($request->get('gym_additional_name'), $request->get('gym_additional_value'));
        if ( !empty($id) ) {
            self::removeHasManyRelationData($request, $saveModel);
        }
        if ( !empty($gymAddtionalDatas) ) {
            $gymAddtionalModels = [];
            foreach ($gymAddtionalDatas as $gymAddtionalData) {
                $gymAddtionalModels[] = new GymAdditional($gymAddtionalData);
            }
            $saveModel->gym_additionals()->saveMany($gymAddtionalModels);
        }
        $gymImagesDatas = self::gymImages($request->get('gym_images'));

        if ( !empty($gymImagesDatas) ) {
            $gymImageModels = [];
            foreach ($gymImagesDatas as $gymImagesData) {
                $gymImageModels[] = new GymImage($gymImagesData);
            }
            $saveModel->gym_images()->saveMany($gymImageModels);
        }

        $gymOpenTimes = self::gymOpenTimes($request->get('gym_open_time_range'), $request->get('gym_open_time'), $request->get('gym_close_time'));
        if ( !empty($gymOpenTimes) ) {
            $gymOpenTimesModels = [];
            foreach ($gymOpenTimes as $gymOpenTime) {
                $gymOpenTimesModels[] = new GymOpenTiming($gymOpenTime);
            }
            $saveModel->gym_open_timings()->saveMany($gymOpenTimesModels);
        }

        return $saveModel;
    }

    private static function removeHasManyRelationData($request, $gymModel)
    {
        $gymModel->update($request->all());
        $gymModel->gym_additionals()->delete();
        $gymModel->gym_open_timings()->delete();
        $gymImagesCollections = $gymModel->gym_images()->get();
        $formImgDatas = $request->get('gym_images');
        foreach ($gymImagesCollections as $gymImagesCollection) {
            $imgNotFound = true;
            $filename = $gymImagesCollection->image;
            foreach ($formImgDatas as $key => $imgData) {
                $formImgname = $imgData['image'];
                if ( empty($imgData['image_form']) ) {
                    if ( $filename == $formImgname ) {
                        $imgNotFound = false;
                    }
                }
            }
            if ( $imgNotFound ) {
                Storage::disk('gym_images')->delete($filename);
            }
        }
        $gymModel->gym_images()->delete();
    }

    public static function getGymModel($request, $id)
    {
        return empty($id) ? self::create($request->all()) : self::find($id);
    }


    private static function gymOpenTimes($timeRanges, $openTimes, $closeTimes)
    {
        $finalTimes = array();
        if ( !empty($timeRanges) ) {
            $i = 0;
            foreach ($timeRanges as $key => $timeRange) {
                if ( !empty($timeRange) && !empty($openTimes[$key]) && !empty($closeTimes[$key]) ) {
                    $finalTimes[$i]['range_id'] = $timeRange;
                    $finalTimes[$i]['open_time_slot_id'] = $openTimes[$key];
                    $finalTimes[$i]['close_time_slot_id'] = $closeTimes[$key];
                    $i++;
                }
            }
        }
        return $finalTimes;
    }

    private static function gymImages($imgDatas)
    {
        $finalImages = array();
        if ( !empty($imgDatas) ) {
            $f = 0;
            foreach ($imgDatas as $key => $imgData) {
                $filename = $imgData['image'];
                if ( !empty($imgData['image_form']) ) {
                    $image = base64_decode($imgData['image_form']);
                    Storage::disk('gym_images')->put($filename, $image);
                }
                $finalImages[$f]['image'] = $filename;
                $finalImages[$f]['position'] = $imgData['position'];
                $f++;
            }
        }
        return $finalImages;
    }

    private static function gymAddtionalData($labels, $datas)
    {
        $finalData = array();
        $i = 0;
        if ( !empty($labels) ) {
            foreach ($labels as $key => $label) {
                if ( !empty($label) && !empty($datas[$key]) ) {
                    $finalData[$i]['name'] = $label;
                    $finalData[$i]['value'] = $datas[$key];
                    $i++;
                }
            }
        }
        return $finalData;
    }

    public static function findByGeo($param)
    {
        $lat = $param['latitude'];
        $lng = $param['longitude'];
        if ( empty($lat) || empty($lng) ) {
            $geocode = GoogleMaps::geocodeAddress($param['address']);
            $lat = $geocode['lat'];
            $lng = $geocode['lng'];
        }
        $callback = function ($query) {
            $query->where('active', '=', 1);
        };
        $callbackEmpty = function ($query) {
        };
        $query = self::distance($lat, $lng);
        $results = $query->with(['equipment' => $callback, 'feature' => $callback, 'gym_images' => $callback, 'gym_open_timings' => $callback, 'gym_additionals' => $callbackEmpty, 'gym_open_timings' => $callbackEmpty])->groupBy('gyms.id')->havingRaw('distance <=' . env('MAX_DISTANCE'))->orderBy('distance', 'ASC')->get();
        $finalData['location'] = ['lat' => $lat, 'lng' => $lng];
        $finalData['results'] = $results;
        return $finalData;
    }

    public static function getGymOpeningTime($gym_id, $day_id)
    {
        $gymOpenTimeSlot = array();
        $dayCallback = function ($query) use ($day_id) {
            $query->where('days.id', '=', $day_id);
        };
        $gymObj = self::where('id', $gym_id)->with(['gym_open_timings'])->get()->first();
        foreach ($gymObj->gym_open_timings as $gymTimeObj) {
            $rangeObj = Range::where('id', $gymTimeObj->Ranges->id)->with(['day' => $dayCallback])->get()->first();
            if ( !empty($rangeObj->day) && $rangeObj->day->count() > 0 ) {
                $gymOpenTimeSlot['open_time'] = $gymTimeObj->open_time_slot->time_val;
                $gymOpenTimeSlot['close_time'] = $gymTimeObj->close_time_slot->time_val;
                break;
            }
        }
        return $gymOpenTimeSlot;
    }
}
