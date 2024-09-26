<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    //
    public function storeRole(Request $request){

        $validate = $request->validate([
            'name' =>'required'
        ]);
        Role::create($validate);
        return response()->json("Successfully created!");
        
    }   
}
