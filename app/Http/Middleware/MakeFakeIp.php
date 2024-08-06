<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MakeFakeIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        //не удалось переопределить метод ip() в глобальной функции request()
        //пришлось обойтись более простым решением
        //ниже код "подменяет" ip. Точнее, значение ложного ip заносится в request()->ip
        $fakeIp = $request->input('fakeIp');
        //var_dump($fakeIp);
        if(!is_null($fakeIp)) {request()->merge(['ip' => $fakeIp]);}
        else {request()->merge(['ip' => request()->ip()]);}
        //echo request('ip');
        return $next($request);
    }
}
