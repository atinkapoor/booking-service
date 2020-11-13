<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

trait RESTActions
{

    public function all()
    {
        $m = self::MODEL;
        return $this->respond(Response::HTTP_OK, $m::all());
    }

    public function allActive($field, $val)
    {
        $m = self::MODEL;
        return $this->respond(Response::HTTP_OK, $m::where($field, $val)->get());
    }

    public function find($cond = [], $orderby = [], $groupby = [], $whereBetween = [], $whereRaw = '', $orConds = [], $pagination = [])
    {
        $m = self::MODEL;
        $query = $m::query();
        if ( !empty($cond) ) {
            $query->where($cond);
        }
        if ( !empty($whereBetween) ) {
            $query->whereBetween($whereBetween['field'], $whereBetween['between']);
        }
        if ( !empty($whereRaw) ) {
            $query->whereRaw($whereRaw);
        }
        if ( !empty($orderby) ) {
            foreach ($orderby['sort_criteria'] as $column => $direction) {
                $query->orderBy($column, $direction);
            }
        }
        if ( !empty($groupby) ) {
            $query->groupBy($groupby['field']);
        }
        if ( !empty($orConds) ) {
            $query->where(function ($query) use ($orConds) {
                foreach ($orConds as $orCond) {
                    $query->orwhere($orCond[0], $orCond[1], $orCond[2]);
                }
            });
        }
        if ( !empty($pagination) ) {
            $k = $query->paginate($pagination['limit']);
            $result['result'] = $query->get();
            $result['totalPages'] = $k->lastPage();
            $result['links'] = $k->links();
        } else {
            $result = $query->get();
        }


        return $this->respond(Response::HTTP_OK, $result);
    }

    public function get($id)
    {
        $m = self::MODEL;
        $model = $m::find($id);
        if ( is_null($model) ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        return $this->respond(Response::HTTP_OK, $model);
    }

    public function findBySlug($cond)
    {
        $m = self::MODEL;
        $query = $m::query();
        if ( !empty($cond) ) {
            $query->where($cond);
        }
        $model = $query->get();

        if ( $model->count() == 0 ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        return $this->respond(Response::HTTP_OK, $model);
    }

    public function add(Request $request)
    {
        $m = self::MODEL;
        return $this->respond(Response::HTTP_OK, $m::create($request->all()));
    }

    public function addWithOutValidation(Request $request)
    {
        $m = self::MODEL;
        return $this->respond(Response::HTTP_CREATED, $m::create($request->all()));
    }

    public function addRelationalData($request)
    {
        $m = self::MODEL;
        $saveModel = $m::saveRelationalData($request);
        return $this->respond(Response::HTTP_OK, $saveModel);
    }

    public function updateRelationalData(Request $request, $id)
    {
        $m = self::MODEL;
        $saveModel = $m::saveRelationalData($request, $id);
        return $this->respond(Response::HTTP_OK, $saveModel);
    }


    public function put(Request $request, $id)
    {
        $m = self::MODEL;
        $model = $m::find($id);
        if ( is_null($model) ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        $model->update($request->all());
        return $this->respond(Response::HTTP_OK, $model);
    }

    public function custom_add_update($data, $id)
    {
        $m = self::MODEL;
        $model = $m::custom_add_update($data, $id);
        return $this->respond(Response::HTTP_OK, $model);
    }

    public function remove($id)
    {
        $m = self::MODEL;
        if ( is_null($m::find($id)) ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        $m::destroy($id);
        return $this->respond(Response::HTTP_OK);
    }

    public function custom_remove($request, $id)
    {
        $m = self::MODEL;
        $m::customRemove($request, $id);
        return $this->respond(Response::HTTP_OK);
    }

    public function findFirst()
    {
        $m = self::MODEL;
        $model = $m::get()->first();
        if ( is_null($model) ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        return $this->respond(Response::HTTP_OK, $model);
    }

    public function getRelationalData($relationtables, $parantCond = array(), $orConds = array(), $betweenConds = array(), $whereHasConds = array(), $orderby = array(), $pagination = array(), $whereRaw = array(), $getCount = 0)
    {
        $m = self::MODEL;
        $modelObj = $m::with($relationtables);
        if ( !empty($whereHasConds) ) {
            foreach ($whereHasConds as $k => $v) {
                $modelObj = $modelObj->whereHas($k, $v);
            }
        }
        if ( !empty($parantCond) ) {
            $modelObj = $modelObj->where($parantCond);
        }
        if ( !empty($orConds) ) {
            $modelObj = $modelObj->where(function ($modelObj) use ($orConds) {
                foreach ($orConds as $orCond) {
                    $modelObj = $modelObj->orWhere($orCond[0], $orCond[1], $orCond[2]);
                }
            });
        }
        if ( !empty($betweenConds) ) {
            $modelObj = $modelObj->whereBetween($betweenConds['field'], $betweenConds['between']);
        }
        if ( !empty($whereRaw) ) {
            $modelObj = $modelObj->whereRaw($whereRaw);
        }
        if ( !empty($orderby) ) {
            foreach ($orderby['sort_criteria'] as $column => $direction) {
                $modelObj = $modelObj->orderBy($column, $direction);
            }
        }

        if($getCount == 1) {
            return $modelObj->count();
        }

        if ( !empty($pagination) ) {
            $k = $modelObj->paginate($pagination['limit']);
            $result['result'] = $modelObj->get();
            $result['totalPages'] = $k->lastPage();
            $result['links'] = $k->links();
        } else {
//DB::enableQueryLog();
            $result = $modelObj->get();
//dd(DB::getQueryLog());
        }

        if ( is_null($result) ) {
            return $this->respond(Response::HTTP_NOT_FOUND);
        }
        return $this->respond(Response::HTTP_OK, $result);
    }

    protected function respond($status, $data = [])
    {
        return response()->json($data, $status);
    }

    public function geolocation($params)
    {
        $m = self::MODEL;
        $data = $m::findByGeo($params);
        return $this->respond(Response::HTTP_OK, $data);
    }

    public function findModel($cond = [], $orderby = [], $groupby = [], $whereBetween = [], $whereRaw = '')
    {
        $m = self::MODEL;
        $query = $m::query();
        if ( !empty($cond) ) {
            $query->where($cond);
        }
        if ( !empty($whereBetween) ) {
            $query->whereBetween($whereBetween['field'], $whereBetween['between']);
        }
        if ( !empty($whereRaw) ) {
            $query->whereRaw($whereRaw);
        }
        if ( !empty($orderby) ) {
            $query->orderBy($orderby['field'], $orderby['direction']);
        }
        if ( !empty($groupby) ) {
            $query->groupBy($groupby['field']);
        }
        return $query->get();
    }

    public function getRelationalDataModel($relationtables, $parantCond = array(), $orConds = array())
    {
        $m = self::MODEL;
        $modelObj = $m::with($relationtables);
        if ( !empty($parantCond) ) {
            $modelObj = $modelObj->where($parantCond);
        }
        if ( !empty($orConds) ) {
            foreach ($orConds as $orCond) {
                $modelObj = $modelObj->orwhere($orCond[0], $orCond[1], $orCond[2]);
            }
        }
        return $modelObj->get();
    }

    public function findGroupStats($request)
    {
        $m = self::MODEL;
        $saveModel = $m::findGroupStats($request);
        return $this->respond(Response::HTTP_OK, $saveModel);
    }
}
