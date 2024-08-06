<?php

namespace App\Http\Controllers;


use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class SearchController extends Controller
{

    public function index() //: void
    {
        echo view('welcome');
        //return view('welcome');
    }

    public function search(Request $request) //: void
    {
    $namePlace = $request->input('namePlace');
        is_null($namePlace) ? $this->index() : $this->map($namePlace);
    }

    //получает запрос от пользовтеля и через API Геокодер находит возможные искомые объекты
    public function map($namePlace)
    {
        //код для получения истории из базы, до сделанного запроса [чтобы еще не показывать его
        //сразу же при первом поиске]
        $guest = new Guest();
        $requests = json_decode($guest->searchHistory());


        $response = Http::get('https://geocode-maps.yandex.ru/1.x/?apikey='.env('API_GEOCODER_KEY').'&geocode=' . $namePlace . '&format=json');

        //задача возможного перехвата ошибки от внешнего сервиса еще не решена!!!
        /*$response->onError(function (Response $response) {
            session()->put('msg2', 'Сторонний сервер не ответил вовремя. Попробуйте повторить позже!');
            return $this->index();
        });*/

        $sorted_data = $this->sortedForMoscow(json_decode($response));
        //var_dump($sorted_data);

        if(empty($sorted_data)) { $this->createMessage(); return $this->index(); }  $founded_place = $this->findPrecisePlace($sorted_data);
        //var_dump($founded_place);

        if(empty($founded_place)) { return $this->index(); }  $coordinates = $founded_place->GeoObject->Point->pos;
        //var_dump($coordinates);

        //проверяем если ли координаты у объекта [не у всех они могут быть]
        is_null($coordinates) ? $this->index() : $response = $this->findNearPlaces($coordinates);
        $sorted_data2 = json_decode($response)->response->GeoObjectCollection->featureMember;

        $sorted_data = $this->renameToRussian($sorted_data);
        $sorted_data2 = $this->renameToRussian($sorted_data2);

        //убирает в $sorted_data2 результаты которые повторяют $sorted_data
        foreach ($sorted_data2 as $key2 => $value2) {
            foreach ($sorted_data as $value) {
                if ($value->GeoObject->metaDataProperty->GeocoderMetaData->text == $value2->GeoObject->metaDataProperty->GeocoderMetaData->text)
                {unset($sorted_data2[$key2]);}
            }
        }

        //обрабатывает историю запросов
        $this->guest();

        echo view('welcome', ['list' => $sorted_data, 'list2' => $sorted_data2, 'history' => $requests]);
    }

    //находит Первый из результатов от API Геокодер [но обрезанных функцией sortedForMoscow()] место
    // с пометкой 'kind' равной 'street' или 'house' и т.д.
    public function findPrecisePlace($data) : object | NULL
    {
    return Arr::first($data, function($value) {
    return $value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'street' || 'house' || 'metro' || 'district';
    } );
    }

    //отбирает только результаты по городу Москва [данные выходят обрезанными]
    public function sortedForMoscow($data) : array
    {
        $data = $data->response->GeoObjectCollection->featureMember;
        return Arr::where($data, function ($value) {
            return str_contains($value->GeoObject->metaDataProperty->GeocoderMetaData->Address->formatted, 'Москва');
        });
    }

    // получает координаты от findPrecisePlace() и по ним находит близлежащие объекты с вариацией по долготе и широте равной 0,035 и 0,035 соответственно
    public function findNearPlaces ($data) : string
    {
        $data = str_replace(' ', ',', $data);
        //var_dump($data);
        return Http::get('https://geocode-maps.yandex.ru/1.x/?apikey='.env('API_GEOCODER_KEY').'&geocode='.$data.'&&ll='.$data.'&rspn=1&spn=0.035,0.035&format=json');
    }

    public function createMessage() : void
    {
        session()->put('msg', 'По району города Москвы ничего не найдено!');
    }

    //переводит обозначение местностей на русский язык
    public function renameToRussian ($data) {
        foreach ($data as $value) {
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'house') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'дом';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'street') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'улица';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'metro') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'метро';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'district') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'район';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'other') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'другое';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'province') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'провинция';}
            if($value->GeoObject->metaDataProperty->GeocoderMetaData->kind == 'locality') {$value->GeoObject->metaDataProperty->GeocoderMetaData->kind = 'местность';}
        }
        return $data;
    }

    //проверяет пользователь новый или он уже был гостем и делал запросы
    //в завиcимости от ответа передает комманду функции insertData по добавлению данных в базу
    public function guest() {
        $guest = new Guest();
        $ip = $guest->isNewGuest();
        is_null($ip) ? $guest->insertData(false) : $guest->insertData(true);
    }

}
