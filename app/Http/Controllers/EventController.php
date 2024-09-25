<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requirement;
use App\Models\Event;
use App\Models\ApiLog;
use App\Models\AcademicYear;
use App\Models\OrganizationalLog;

use App\Http\Requests\EventRequest;

class EventController extends Controller
{

    public function storeEvent(EventRequest $request){

        try{

             if ($this->isExist($request->validated())) {
 
                 $response = [
                     'isSuccess'=> false,
                     'message'=> 'The event you are trying to register already exists. Please verify your input and try again.'
                 ];
 
                 $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
 
                 return response()->json($response, 422);
 
             }else{

                 $exists = AcademicYear::where('name', $request->academic_year)
                            ->exists();

                if(!$exists){
                    AcademicYear::create(['name' => $request->academic_year]);
                }
                
                Event::create($request->validated());
          
                $eventid= $this->getEventID($request->name,$request->description,$request->academic_year,$request->submission_date);

                foreach($request->requirements as $requirement){
                    Requirement::create(['name' => $requirement['name'],
                                        'org_log_id' => $requirement['org_log_id'],
                                        'event_id' => $eventid
                    ]);

                }
                
                 $response = [
                           'isSuccess' => true,
                            'message' => "Successfully created."
                     ];

                 $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
                 return response()->json($response);
             }
 
              
          }catch (Exception $e) {
  
              $response = [
                  'isSuccess' => false,
                  'message' => "Unsucessfully created. Please check your inputs.",
                  'error' => 'An unexpected error occurred: ' . $e->getMessage()
             ];
  
              $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
              return response()->json($response, 500);
  
          }
 
    }

    public function editEvent(Request $request){

        try{
            $data = Event::find($request->id);
            $response = [
                'isSuccess' => true,
                    'data' => $data
            ];
   
           $this->logAPICalls('editEvent', "", $request->all(), [$response]);
           return response()->json($response);
   
        }catch(Exception $e){
   
            $response = [
                   'isSuccess' => false,
                   'message' => "Unsuccessfully edited. Please check your inputs.",
                   'error' => 'An unexpected error occurred: ' . $e->getMessage()
              ];
   
               $this->logAPICalls('editEvent', "", $request->all(), [$response]);
               return response()->json($response, 500);
        }
          
    }

    public function updateEvent(Request $request){

        try{

            $validate = $request->validate([
                'org_log_id' => 'required',
                'name' => 'required'
            ]);
            
            $exist = Event::where('name',$validate['name'])
                            ->where('org_log_id',$validate['org_log_id'])
                            ->exists();
            if($exist) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The event you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
    
                return response()->json($response, 422);
    
            }else{
    
                $event = Event::find($request->id);
                $event->update(['name' => $validate['name']]);
           
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully updated."
                ];
    
                $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
                return response()->json($response);
            }

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    }

    public function deleteEvent(Request $request){

        try{

            $event = Event::find($request->id);
            $event->update(['status' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "Successfully deleted."
            ];

            $this->logAPICalls('deleteEvent', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('deleteEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }

    }

    public function getEvent(Request $request){

        try{

            $data = Event::where('status', 'A')
            ->where('organizational_log_id', $request->organizational_log_id)
            ->orderBy('created_at', 'desc') 
            ->take(10)
            ->get();
    
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];
    
            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 200);

        }catch(Throwable $ex){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }

    public function getAcademicYear(){
        try{
            $data = AcademicYear::all();
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];
    
            $this->logAPICalls('getAcademicYear', "", [], [$response]);
            return response()->json($response, 200);

        }catch(Throwable $ex){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getAcademicYear', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }

    public function searchEvent(Request $request){
        try{
            
            $query = $request->input('query');
            $academic_year = $request->input('academic_year');
            $org_id = $request->input('org_log_id');

            if(!empty($query) && empty($academic_year) ){

                $results = Event::where('org_log_id', $org_id)
                ->when($query, function ($q) use ($query) {
                    return $q->where(function ($queryBuilder) use ($query) {
                        $queryBuilder->where('name', 'LIKE', "%{$query}%");
                    });
                })->get();

                $response = [
                    'isSuccess' => true,
                    'results' => $results
                ];

        
            }elseif(empty($query) && !empty($academic_year)){

                $results = Event::where('organizational_log_id', $org_id)
                                ->where('academic_year',$academic_year)
                                ->get();

                $response = [
                    'isSuccess' => true,
                    'results' => $results
                ];

            }else{

                $results = Event::where('org_log_id', $org_id)
                                ->where('academic_year', $academic_year)
                ->when($query, function ($q) use ($query) {
                    return $q->where(function ($queryBuilder) use ($query) {
                        $queryBuilder->where('name', 'LIKE', "%{$query}%");
                    });
                })->get();

                $response = [
                    'isSuccess' => true,
                    'results' => $results
                ];

            }
            
             // $this->logAPICalls('searchEvent', "",$request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Search failed. Please try again later.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('searchEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function viewEvent(Request $request){

        try{

            $data = Event::find($request->id);
            return $data;

        }catch(Exception $e){
            
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('viewEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }
    

    public function getEventID($name,$descrip,$acadyear,$submdate){
        $event= Event::where('name',$name)
                      ->where('description',$descrip)
                      ->where('academic_year',$acadyear)
                      ->where('submission_date',$submdate)
                      ->get();

        return $event->first()->id;
    }


    public function isExist($validate){

        return Event::where('org_log_id', $validate['org_log_id'])
        ->where('name', $validate['name'])
        ->where('description', $validate['description'])
        ->where('academic_year', $validate['academic_year'])
        ->where('submission_date', $validate['submission_date'])
        ->exists();

    }

    public function logAPICalls(string $methodName, string $userId, array $param, array $resp){
        try
        {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' =>  json_encode($param),
                'api_response' =>  json_encode($resp)
            ]);
        }
        catch(Throwable $ex){
            return false;
        }
        return true;
    }

}
