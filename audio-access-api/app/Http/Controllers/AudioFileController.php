<?php

namespace App\Http\Controllers;

use App\Models\AudioFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use getID3;

class AudioFileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'title' => 'required|max:100',
            'audio_file' => 'required|file|mimes:mp3,wav,ogg,flac|max:50000'
        ]);

        $file = $request->file('audio_file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('audio_files', $fileName, 'private');
        
        // Get audio duration using getID3 library
        $getID3 = new getID3();
        $fileInfo = $getID3->analyze(storage_path('app/private/' . $filePath));
        $duration = isset($fileInfo['playtime_seconds']) ? round($fileInfo['playtime_seconds']) : 0;
        
        $audioFile = new AudioFile();
        $audioFile->title = $request->input('title');
        $audioFile->filename = $fileName;
        $audioFile->file_path = $filePath;
        $audioFile->file_size = $file->getSize();
        $audioFile->duration = $duration;
        $audioFile->user_id = Auth::id();
        $audioFile->save();
        
        return response()->json([
            'message' => 'File uploaded successfully',
            'file_id' => $audioFile->id
        ], 201);
    }
    
    public function list()
    {
        $files = AudioFile::where('user_id', Auth::id())->get();
        return response()->json(['files' => $files]);
    }
    
    public function delete($id)
    {
        $file = AudioFile::findOrFail($id);
        
        // Check if user owns this file
        if ($file->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Delete from storage
        Storage::disk('private')->delete($file->file_path);
        
        // Delete from database
        $file->delete();
        
        return response()->json(['message' => 'File deleted successfully']);
    }
}