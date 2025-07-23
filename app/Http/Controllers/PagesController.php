<?php

namespace App\Http\Controllers;

use App\Models\Pages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagesController extends Controller
{
    public function __construct()
    {
        // Debug SQL queries
        \DB::listen(function ($query) {
            \Log::info('SQL Query: ' . $query->sql);
            \Log::info('SQL Bindings: ', $query->bindings);
            \Log::info('SQL Time: ' . $query->time . 'ms');
        });
    }

    //

    function privacypolicy(Request $request){
        $data = Pages::first();
       return $data->privacy;
    }
    function termsOfUse(Request $request){
        $data = Pages::first();
       return $data->termsofuse;
    }

    function viewTerms(Request $request){
        $data = Pages::first();
        return view('pages.viewTerms',['data'=> $data->termsofuse]);
    }
    function updatePrivacy(Request $request){
        $data = Pages::first();
        $data->privacy= $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }
    function updateTerms(Request $request){
        $data = Pages::first();
        $data->termsofuse= $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }
    function viewPrivacy(){

        $data = Pages::first();
        return view('pages.viewPrivacy',['data'=> $data->privacy]);
    }
}
