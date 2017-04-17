<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\HostedFile;

class HostedFileController extends Controller
{
    protected $fillable = ['path', 'file_data', 'file_name', 'file_mime', 'action', 'hosted_site_id', 'uidvar'];
    
    public function __construct()
    {
        $this->middleware('auth', ['except' => 'catchall']);
    }
    
    public function index()
    {
        $files = HostedFile::whereNull('hosted_site_id')->get();
        return view('files.index')->with('files', $files);
    }
    
    public function catchall(Request $request)
    {
        $file = HostedFile::grab($request->path());
        if ($file === null)
        {
            abort(404);
        }
        else
        {
            $file->serve($request);
        }
        return;
    }
    
    public function addfile(Request $request)
    {
        $this->validate($request, [
            'attachment' => 'required|file',
            'action' => 'required|integer',
        ]);
        $path = '/';
        if ($request->has('path'))
            $path = $request->input('path');
        if (HostedFile::path_already_exists($path))
            return back()->withErrors('This route already exists! Choose another one');
        $pathinfo = pathinfo($request->input('path'));
        $dirname = '';
        if (isset($pathinfo['dirname']) && $pathinfo['dirname'] != '.')
            $dirname = $pathinfo['dirname'];
        $file = $request->file('attachment');
        $newfile = new HostedFile();
        $newfile->route = $dirname;
        $newfile->file_name = $pathinfo['basename'];
        $newfile->action = $request->input('action');
        $newfile->original_file_name = $file->getClientOriginalName();
        $newfile->file_mime = $file->getMimeType();
        if ($request->has('kill_switch') && $request->input('kill_switch') > 0)
            $newfile->kill_switch = $request->input('kill_switch');
        else
            $newfile->kill_switch = null;
        if ($request->has('uid_tracker') && $request->input('uid_tracker') != '')
            $newfile->uidvar = $request->input('uid_tracker');
        else
            $newfile->uidvar = null;
        $newfile->invalid_action = HostedFile::INVALID_ALLOW;
        if ($request->has('invalid_action'))
            $newfile->invalid_action = $request->input('invalid_action');
        $newfile->notify_access = $request->has('notify');
        $newfile->hosted_site_id = null;
        $newfile->local_path = $file->storeAs('hosted', sha1(time().''.rand()).'.dat');
        $newfile->save();
        return back()->with('success', 'File successfully hosted!');
    }
    
    public function deletefile(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|integer'
        ]);
        $file = HostedFile::findorfail($request->input('file'));
        unlink(storage_path('app/'.$file->local_path));
        $file->delete();
        return back()->with('success', 'File deleted successfully!');
    }
}