<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requirement;
use App\Models\Event;
use App\Models\OrganizationLog;
use App\Models\Program;
use App\Models\ApiLog;
use App\Models\AcademicYear;
use App\Models\OrganizationalLog;

use App\Http\Requests\EventRequest;

class EventController extends Controller
{

    public function getEvent(Request $request){

        try{

            $validated = $request->validate([
                'org_log_id' => 'required|exists:organizational_logs,id',
                'search' => 'nullable|string',
                'academic_year' => 'nullable|string' 
            ]);
            
            $query = Event::where('org_log_id', $validated['org_log_id'])
                    ->orderBy('created_at', 'desc');
            
            if (!empty($validated['search'])) {
                $query->where('name', 'like', '%' . $validated['search'] . '%')
                ->orderBy('created_at', 'desc');
            }
            
            if (!empty($validated['academic_year'])) {
                $query->where('academic_year', $validated['academic_year'])
                ->orderBy('created_at', 'desc');
            }
            
            $data = $query->get();

            $response = [
                'isSuccess' => true,
                'data' => $data
           ];

            $this->logAPICalls('getEvent', "", $request->all(), [$response]);
            return response()->json($data);
            
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

                $invalidRequirements = [];

                foreach ($request->requirements as $requirement) {
                    if (!OrganizationalLog::find($requirement['org_log_id'])) {
              
                        $invalidRequirements[] = $requirement;
                        continue; 
                    }

                    Requirement::create([
                        'name' => $requirement['name'],
                        'org_log_id' => $requirement['org_log_id'],
                        'event_id' => $eventid,
                    ]);
                }
                
                 $response = [
                           'isSuccess' => true,
                            'message' => "Successfully created.",
                            'invalid_requirements' => $invalidRequirements
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

            $request->validate([

                'id' => 'required|exists:events'
            ]);

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

            $validated = $validate = $request->validate([
                'id' => 'required|exists:events,id',
                'name' => 'required'
            ]);
            
            $data = Event::find($validated['id']);

            $exist = Event::where('name',$validate['name'])
                            ->where('org_log_id',$data->org_log_id)
                            ->exists();
            if($exist) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The event you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
                return response()->json($response, 422);
    
            }else{

                $data ->update(['name' => $validate['name']]);
           
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

            $validated = $validate = $request->validate([
                'id' => 'required|exists:events,id'
            ]);

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

    public function viewEvent(Request $request){

        try{
            $validated = $request->validate([
                'id' => 'required|exists:events'
            ]);

            $data = Event::find($request->id);

            $response = [
                'isSuccess' => true,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('viewEvent', "", $request->all(), [$response]);
            return response()->json($response);
       

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
