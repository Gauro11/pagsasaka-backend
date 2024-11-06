<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\RequirementFile;

class MonitorFiles extends Command
{
    protected $signature = 'monitor:files';
    protected $description = 'Monitor files in the storage';

    public $previousFiles_ino = [];

    public function handle()
    {
     //   while (true) {
          
           $currentFiles = Storage::allFiles('public');
           $currentDirectories = $this->getAllDirectories('public');

          
            $allCurrentItems = array_merge($currentFiles, $currentDirectories);

         
            $previousFiles = $this->getPreviousFiles();

            // Clear previous inodes array
            $this->previousFiles_ino = [];
          
            $this->compareFiles($allCurrentItems, $previousFiles);

   
            $this->saveCurrentFiles($allCurrentItems);
            $this->previousFiles_ino=[];
            
     
          //  sleep(1);
       // }
    }

    protected function getPreviousFiles()
    {
 
        return json_decode(Storage::get('previous_files.json'), true) ?? [];
    }

    protected function compareFiles($currentFiles, $previousFiles)
    {
        $deleted = [];
        $added = [];
        $rename = [];

      
        foreach ($currentFiles as $currentFile) {
            if (!in_array($currentFile, $previousFiles)) {
                $added[] = $currentFile;
            }
        }

        foreach ($previousFiles as $previousFile) {
            if (!in_array($previousFile, $currentFiles)) {
                $deleted[] = $previousFile;
            }
        }

   
        if ($added) {
           
                foreach ($added as $addedFile) {

                    $data = RequirementFile::where('ino', stat(Storage::path($addedFile))['ino'] )->first();
                    $relativePath = str_replace('public/', '', $addedFile);
                    if($data){

                        $data->update([
                            'path' => str_replace('public/', '', $addedFile),
                            'filename' => basename($addedFile)
                        ]);

                    }
                }
        }

        if ($deleted) {
            $ino_file = null;

           foreach($deleted as $deleteFile){

                $normalizedDeleteFile = str_replace('\\', '/', $deleteFile);
                $normalizedPath = str_replace('public/', '', $normalizedDeleteFile);

                     $data = RequirementFile::where('path',  $normalizedPath)->first();

                     if($data){

                        $data->delete($data);

                    }

           }

           Log::info('Deleted files/folders: ', [$deleted,$normalizedDeleteFile]);
        }

        if ($rename) {
            Log::info('Renamed: ', $rename);
        } else {
            Log::info('No changes detected.');
        }
    }

    protected function saveCurrentFiles($currentFiles)
    {
        Storage::put('previous_files.json', json_encode($currentFiles));
    }

    public function getAllDirectories($path)
    {
        $directories = Storage::directories($path);
        $allDirectories = $directories; // Start with direct directories
        
        foreach ($directories as $directory) {
            // Recursively find directories inside the current directory
            $nestedDirectories = $this->getAllDirectories($directory);  // Corrected to use $this
            $allDirectories = array_merge($allDirectories, $nestedDirectories);
        }

        return $allDirectories;
    }
}
