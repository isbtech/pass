<?php

namespace App\Http\Controllers;

use App\Models\AccessCode;
use App\Models\AudioFile;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccessCodeController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'audio_file_id' => 'required|exists:audio_files,id',
            'validity_type' => 'required|in:hours,days,playtime',
            'validity_value' => 'required|integer|min:1',
            'max_plays' => 'nullable|integer|min:1'
        ]);
        
        $audioFile = AudioFile::findOrFail($request->input('audio_file_id'));
        
        // Check if user owns this file
        if ($audioFile->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Generate unique code
        $code = Str::random(10);
        while (AccessCode::where('code', $code)->exists()) {
            $code = Str::random(10);
        }
        
        $accessCode = new AccessCode();
        $accessCode->code = $code;
        $accessCode->audio_file_id = $request->input('audio_file_id');
        $accessCode->validity_type = $request->input('validity_type');
        $accessCode->validity_value = $request->input('validity_value');
        $accessCode->max_plays = $request->input('max_plays');
        
        // Set expiration date if validity type is hours or days
        if ($request->input('validity_type') === 'hours') {
            $accessCode->expires_at = Carbon::now()->addHours($request->input('validity_value'));
        } elseif ($request->input('validity_type') === 'days') {
            $accessCode->expires_at = Carbon::now()->addDays($request->input('validity_value'));
        }
        
        $accessCode->save();
        
        return response()->json([
            'message' => 'Access code generated successfully',
            'code' => $code,
            'expires_at' => $accessCode->expires_at
        ], 201);
    }
    
    public function listCodes()
    {
        $codes = AccessCode::with('audioFile')
            ->whereHas('audioFile', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->get();
            
        return response()->json(['access_codes' => $codes]);
    }
    
    public function deactivate($id)
    {
        $code = AccessCode::findOrFail($id);
        
        // Check if user owns this code's file
        if ($code->audioFile->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $code->is_active = false;
        $code->save();
        
        return response()->json(['message' => 'Access code deactivated']);
    }
    
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);
        
        $code = $request->input('code');
        $accessCode = AccessCode::where('code', $code)
            ->where('is_active', true)
            ->first();
        
        if (!$accessCode) {
            return response()->json(['message' => 'Invalid or expired access code'], 404);
        }
        
        // Check if expired (for hours/days validity)
        if ($accessCode->expires_at && Carbon::now()->gt($accessCode->expires_at)) {
            return response()->json(['message' => 'Access code has expired'], 403);
        }
        
        // Check max plays if set
        if ($accessCode->max_plays) {
            $playCount = $accessCode->accessLogs()->count();
            if ($playCount >= $accessCode->max_plays) {
                return response()->json(['message' => 'Maximum plays reached'], 403);
            }
        }
        
        // Get file details
        $audioFile = $accessCode->audioFile;
        
        // Log the access
        $accessLog = new AccessLog();
        $accessLog->access_code_id = $accessCode->id;
        $accessLog->ip_address = $request->ip();
        $accessLog->user_agent = $request->userAgent();
        $accessLog->save();
        
        return response()->json([
            'valid' => true,
            'file_info' => [
                'title' => $audioFile->title,
                'duration' => $audioFile->duration,
                'id' => $audioFile->id,
                'log_id' => $accessLog->id
            ]
        ]);
    }
    
    public function getAudioFile(Request $request, $id)
    {
        $request->validate([
            'code' => 'required|string',
            'log_id' => 'required|integer'
        ]);
        
        $code = $request->input('code');
        $logId = $request->input('log_id');
        
        $accessCode = AccessCode::where('code', $code)
            ->where('is_active', true)
            ->first();
        
        if (!$accessCode || $accessCode->audio_file_id != $id) {
            return response()->json(['message' => 'Invalid access'], 403);
        }
        
        // Verify the log exists and matches
        $log = AccessLog::find($logId);
        if (!$log || $log->access_code_id != $accessCode->id) {
            return response()->json(['message' => 'Invalid session'], 403);
        }
        
        $audioFile = AudioFile::find($id);
        if (!$audioFile) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        // Stream the file with security headers
        return response()->stream(
            function() use ($audioFile) {
                $path = storage_path('app/private/' . $audioFile->file_path);
                $stream = fopen($path, 'rb');
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => 'audio/mpeg',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Accept-Ranges' => 'none', // Disable range requests
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
    
    public function updatePlayStats(Request $request)
    {
        $request->validate([
            'log_id' => 'required|integer',
            'duration' => 'required|integer',
            'is_complete' => 'required|boolean'
        ]);
        
        $log = AccessLog::find($request->input('log_id'));
        if (!$log) {
            return response()->json(['message' => 'Log not found'], 404);
        }
        
        $log->play_duration = $request->input('duration');
        $log->is_complete = $request->input('is_complete');
        $log->save();
        
        // Check if this was playtime-based access and if playtime is exhausted
        $accessCode = $log->accessCode;
        if ($accessCode->validity_type === 'playtime') {
            $totalPlaytime = $accessCode->accessLogs()->sum('play_duration');
            if ($totalPlaytime >= $accessCode->validity_value) {
                $accessCode->is_active = false;
                $accessCode->save();
            }
        }
        
        return response()->json(['message' => 'Play stats updated']);
    }
    
    public function getStats(Request $request)
    {
        $query = AccessLog::with(['accessCode.audioFile'])
            ->whereHas('accessCode.audioFile', function($query) {
                $query->where('user_id', Auth::id());
            });
        
        // Apply filters
        if ($request->has('file_id')) {
            $query->whereHas('accessCode', function($q) use ($request) {
                $q->where('audio_file_id', $request->input('file_id'));
            });
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('access_time', '>=', $request->input('date_from'));
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('access_time', '<=', $request->input('date_to'));
        }
        
        $logs = $query->orderBy('access_time', 'desc')->get();
        
        return response()->json(['logs' => $logs]);
    }
}