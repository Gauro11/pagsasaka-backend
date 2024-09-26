<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;

class OrgController extends Controller
{
    //
    public function storeOrg(Request $request){

        $validate = $request->validate([
            'name' =>'required'
        ]);
        Organization::create($validate);
        return response()->json("Successfully created!");
        
    }   

}
