<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\News;
use App\Models\Gallery;
use App\Models\Family;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;

class NewsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        
    }

    public function index()
    {
        $newses = News::orderBy('id', 'desc')->get();
        return view('newses.index', compact('newses'));
    }

    public function saveToken(Request $request)
    {
        $affectedRows = Family::where('deleted_at', null)
            ->update(['device_token' => $request->token]);
        return response()->json(['token saved successfully.']);
    }

    public function create()
    {
        $families = Family::all();
        return view('newses.create', compact('families'));
    }

    public function store(Request $request)  
    {

    //    $firebaseConfig = [
    //         'apiKey' => 'AIzaSyB50ZW8U8iGfBpJKq54Y442CtCO7n--h-U',
    //         'authDomain' => 'maheskwari-community.firebaseapp.com',
    //         'projectId' => 'maheskwari-community',
    //         'storageBucket' => 'maheskwari-community.appspot.com',
    //         'messagingSenderId' => '84169887754',
    //         'appId' => '1:84169887754:web:df1f52f3cd68d9546f0ff3',
    //         'measurementId' => 'G-MHZK709NQ5',
    //     ];
        
    //     $factory = (new Factory)
    // ->withServiceAccount($firebaseConfig);

    // $firebase = $factory->createFirestore();
    // $firestore = $firebase->getFirestore();

    //     dd($firestore);
        // validation rules for the form data
        $rules = [
            'title' => 'required|max:255',
            'description' => 'required',
        ];

        // validate the form data
        $validatedData = $request->validate($rules);

        // create a new News object
        $news = new News;
        $news->title = $validatedData['title'];
        $news->description = $validatedData['description'];
        $news->created_by = auth()->user()->id;
        $news->save();

        // upload the file and store the file name in the 'file' column
        if ($request->hasFile('file')) {
            $allowedfileExtension=['APNG','jpg','png','jpeg','avif', 'gif','svg','mp4','mov','avi', 'mkv','wmv' ];
            $files = $request->file('file'); 
            $errors = [];
            $imgnumber = 0;
            foreach ($files as $key => $file) { 
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension,$allowedfileExtension);
                if($check) {
                    $name = 'mpm-'.time().$imgnumber.'.'.$extension;
                    //$file->move(public_path() . '/upload/images/', $name);
                    Storage::disk('s3')->put($name, file_get_contents($file));
                    $imageExtension = ['APNG','jpg','png','jpeg','avif', 'gif','svg'];
                    $videoExtension = ['mp4','mov','avi', 'mkv','wmv'];

                    $checkImg = in_array($extension,$imageExtension);
                    if($checkImg){
                        $fileType = 'image';
                    }
                    $checkImg = in_array($extension,$videoExtension);
                    if($checkImg){
                        $fileType = 'video';
                    }
                    $gallery = new Gallery();
                    $gallery->name = $name; 
                    $gallery->type = $fileType; 
                    $gallery->description =  $validatedData['description'];
                    $gallery->source = 'news'; 
                    $gallery->news_id = $news->id;       
                    $gallery->album_name = $validatedData['title'];
                    $gallery->event_name = $validatedData['title'];
                    $gallery->save();
                
                    
                }
                $imgnumber++;
            }
        }

        $galleries = Gallery::where('deleted_at',null)
                                ->where('news_id', $news->id)
                                ->get(['id', 'name']);
        
        $temporarySignedUrl = Storage::disk('s3')->temporaryUrl($galleries[0]['name'], now()->addMinutes(10));
           
       // dd($temporarySignedUrl);

        // send notification
        $families = $request->input('families');

        $firebaseToken = Family::whereIn('id', $families)->whereNotNull('device_token')->pluck('device_token')->toArray();
        // dd($firebaseToken);
        $SERVER_API_KEY = 'AAAAE5jqkAo:APA91bFsgwMzxnepmUXlA1OV6v98438A_OHJdVDX0NB24ft6MSRezbvRswrSmL5n-FjLpgc1kSzsOr4DiqH1pWcKR6RSvRGzWxhgxxVa5QZXj11JH_YzrAYORRHd4ftswapvuBpYCBlb';

        $data = [
            "registration_ids" => $firebaseToken,
            "notification" => [
                "title" => $request->title,
                "body" => $request->description,
                "imageUrl"=> $temporarySignedUrl,
            ]
        ];
        $dataString = json_encode($data);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);
        //dd($response);
        // save the News object to the database
       

        // redirect to the index page with a success message
        return redirect()->route('newses.index')->with('success', 'News created successfully.');
    }

    public function edit($id)
    {
        $news = News::findOrFail($id);
        return view('newses.edit', compact('news'));
    }

    public function update(Request $request, $id)
    {
        // validation rules for the form data
        $rules = [
            'title' => 'required|max:255',
            'description' => 'required',
        ];

        // validate the form data
        $validatedData = $request->validate($rules);

        // find the News object by its ID
        $news = News::findOrFail($id);
        $news->title = $validatedData['title'];
        $news->description = $validatedData['description'];

        // upload the file and update the 'file' column
        if ($request->hasFile('file')) {
            $allowedfileExtension=['APNG','jpg','png','jpeg','avif', 'gif','svg','mp4','mov','avi', 'mkv','wmv' ];
            $files = $request->file('file'); 
            $errors = [];
            $imgnumber = 0;
            foreach ($files as $key => $file) { 
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension,$allowedfileExtension);
                if($check) {
                    $name = 'mpm-'.time().$imgnumber.'.'.$extension;
                    //$file->move(public_path() . '/upload/images/', $name);
                    Storage::disk('s3')->put($name, file_get_contents($file));
                    $imageExtension = ['APNG','jpg','png','jpeg','avif', 'gif','svg'];
                    $videoExtension = ['mp4','mov','avi', 'mkv','wmv'];

                    $checkImg = in_array($extension,$imageExtension);
                    if($checkImg){
                        $fileType = 'image';
                    }
                    $checkImg = in_array($extension,$videoExtension);
                    if($checkImg){
                        $fileType = 'video';
                    }
                    $gallery = new Gallery();
                    $gallery->name = $name; 
                    $gallery->type = $fileType; 
                    $gallery->description =  $validatedData['description'];
                    $gallery->source = 'news'; 
                    $gallery->news_id = $news->id;       
                    $gallery->album_name = $validatedData['title'];
                    $gallery->event_name = $validatedData['title'];
                    $gallery->save();
                
                    
                }
                $imgnumber++;
            }
        }

        // set the updated_by column
        $news->updated_by = auth()->user()->id;

        // save the News object to the database
        $news->save();

        // redirect to the index page with a success message
        return redirect()->route('newses.index')->with('success', 'News updated successfully.');
    }

    public function destroy($id)
    {
        // find the News object by its ID and delete it
        $news = News::findOrFail($id);
        $news->delete();

        // redirect to the index page with a success message
        return redirect()->route('newses.index')->with('success', 'News deleted successfully.');
    }
}

