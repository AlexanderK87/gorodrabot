<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Guest extends Model
{
    use HasFactory;

    public function isNewGuest() {
        $ip = request('ip');//->ip();
        return DB::table('guests')->where('ip', '=', $ip)->value('ip');
    }
    public function insertData($flag) {

        //$flag со значением true или false - показывает - перед нами новый или уже посещавший сайт
        //true - старый посетитель, false - новый
        if(!$flag) {$array = (array) [ (object) (['time' => date("Y-m-d H:i:s"), 'request' => request('namePlace')]) ];}

        else {
            $array = DB::table('guests')->where('ip', '=', request('ip'))
                                             ->value('requests');
            $array = json_decode($array);

            //вспомогательный массив $arr, с помощью которого проверяется - был ли до этого сделан такой же запрос
            $arr = []; $i=0;
            foreach($array as $value) {$arr[$i] = $value->request; ++$i;}

            //проходит проверка - запрос новый или нет. Если новый, то в виде объекта заносится в
            //переменную, которой позже обновит поле 'requests' пользователя
            if(array_search(request('namePlace'), $arr) === false)
            {
                $obj = (['time' => date("Y-m-d H:i:s"), 'request' => request('namePlace')]);
                array_push($array, $obj);
            }
        }
        //заносит в базу нового пользователя (гостя) или же обновляет уже имеющуюся запись
        DB::table('guests')->updateOrInsert(['ip' => request('ip')], ['requests' => json_encode($array, JSON_UNESCAPED_UNICODE)]);
    }

    //достает историю пользователя из базы
    public function searchHistory() {
        $ip = request('ip');//->ip();
        return DB::table('guests')->where('ip', '=', $ip)->value('requests');
    }

}
