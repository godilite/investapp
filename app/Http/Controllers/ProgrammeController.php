<?php

namespace App\Http\Controllers;
use Session;
use App\Programme;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Event;
use Auth;
use App\User;
use App\WaitingList;
use Carbon\Carbon;
use App\Speaker;
use App\Mail\JoinProgramme;
use App\Mail\JoinApproved;
class ProgrammeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $programmes = Programme::latest()->paginate(10);
        $pending = WaitingList::whereStatus('pending')->count();
        $waiting = WaitingList::latest()->paginate(10);
        return view('admin.programmes.index', compact('programmes','waiting', 'pending'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'desc' => 'required',
            'title' => 'required',
            'duration' => 'required',
            'featured' =>'image',
            'type' =>'required',
            'amount' => 'numeric',
            'venue' => 'required',
            'highlight*' => 'required|array',
            'attendee' => 'required|numeric',
            'startdate' => 'required'
        ]);
        $programme = new Programme();

        $programme->title = $request->title;
        $programme->overview = $request->desc;
        $programme->duration = $request->duration;
        $programme->key_features = json_encode($request->highlight);
        $programme->fee = $request->amount;
        $programme->venue = $request->venue;
        $programme->startdate = $request->startdate;
        $programme->attendee = $request->attendee;
        $programme->type = $request->type;
        $programme->status = 'pending';
        $programme->createdBy = Auth::user()->id;
        $programme->enddate = Carbon::parse($request->startdate)->addDays($request->duration);
        $programme->featured = $request->featured->store('programmes');
        $programme->save();
        Session::flash('success', 'Programme created successfully');
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Programme  $programme
     * @return \Illuminate\Http\Response
     */
    public function show(Programme $programme)
    {
        //
    }

    public function approve(Request $request)
    {
        $waiting = WaitingList::findOrFail($request->id);
        $waiting->status = 'approved';
        $waiting->save();
        $programme = Programme::findOrFail($waiting->programme_id);
        $programme->users()->attach($waiting->user_id);
        $user = User::findOrFail($waiting->user_id);
        Mail::to($user->email)->send(new JoinApproved($programme, $user));
        Session::flash('success', 'Approved Successfully');
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Programme  $programme
     * @return \Illuminate\Http\Response
     */
    public function edit(Programme $programme)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Programme  $programme
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $request->validate([
            'programmeId' => 'required',
            'desc' => 'required',
            'title' => 'required',
            'duration' => 'required',
            'type' =>'required',
            'amount' => 'numeric',
            'venue' => 'required',
            'highlight1' => 'required',
            'highlight2' => 'required',
            'highlight3' => 'required',
            'highlight4' => 'required',
            'highlight5' => 'required',
            'attendee' => 'required|numeric',
            'startdate' => 'required'
        ]);
        $data = [];
         array_push($data,
         $request->highlight1,
         $request->highlight2,
         $request->highlight3,
         $request->highlight4,
         $request->highlight5);

        $programme = Programme::findOrFail($request->programmeId);

        if ($request->hasFile('featured')) {
            $programme->featured = $request->featured->store('programmes');
        }
        $programme->title = $request->title;
        $programme->overview = $request->desc;
        $programme->duration = $request->duration;
        $programme->key_features = json_encode($data);
        $programme->fee = $request->amount;
        $programme->venue = $request->venue;
        $programme->startdate = $request->startdate;
        $programme->attendee = $request->attendee;
        $programme->type = $request->type;
        $programme->enddate = Carbon::parse($request->startdate)->addDays($request->duration);
        if ($programme->createdBy == Auth::user()->id) {
            $programme->save();
        }
        return response()->json($programme, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Programme  $programme
     * @return \Illuminate\Http\Response
     */
    public function destroy(Programme $programme)
    {
        //
    }

    public function joinPage()
    {
        $programmes = Programme::latest()->paginate(6);
        return view('programme', compact('programmes'));
    }

    public function details(Request $request)
    {
        $programme = Programme::findOrFail($request->id);
        $speakers = $programme->comfirmedSpeakers()->get();
        $requested = false;
        $confirmed = false;
        $expired = true;
        $features = json_decode($programme->key_features);
        if (Auth::check()) {
            $whitelist = WaitingList::whereUserId(Auth::user()->id)
                                        ->whereIn('programme_id',[$programme->id])->first();

          if ($whitelist != null) {
            $requested = true ;
            $whitelist->status ==  'approved' ? $confirmed = true: $confirmed = false ;
          }
        }
        if (Carbon::now() < Carbon::parse($programme->enddate)) {
            $expired = false;
         }

        return view('programme-details', compact('programme', 'speakers', 'requested', 'confirmed','features', 'expired'));
    }

    public function sendRequest(Request $request)
    {
        $user = Auth::user();
        $programme = Programme::findOrFail($request->id);
        WaitingList::create([
            'username' => $user->first_name.' '.$user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'programme_title' => $programme->title,
            'user_id' => $user->id,
            'programme_id' => $programme->id
        ]);
        Mail::to($user->email)->send(new JoinProgramme($programme, $user));
        return redirect()->back();
    }

    public function viewCalender(Request $request)
    {
        $programme = Programme::findOrFail($request->id);
        $events = $programme->events()->get();

        return view('admin.programmes.calendar', compact('events'));
    }

    public function userCalendar($id)
    {
        $programme = Programme::findOrFail($id);
        $events = $programme->events()->get();
        return view('users.user-calendar', compact('events'));
    }

    public function programmeDetails(Request $request)
    {
        $programme = Programme::whereId($request->id)->with('speakers')->with('users')->with('creator')->first();
        $requested = false;
        $confirmed = false;
        $features = json_decode($programme->key_features);
        if (Auth::check()) {
            $whitelist = WaitingList::whereUserId(Auth::user()->id)
                                        ->whereIn('programme_id',[$programme->id])->first();

          if ($whitelist != null) {
            $requested = true ;
            $whitelist->status ==  'approved' ? $confirmed = true: $confirmed = false ;
          }
        }
        return response()->json(['programme'=>$programme,
        'requested' =>$requested,
        'confirmed' =>$confirmed,
        'keyFeatures' => $features,
        'events' => $programme->events()->get()
    ], 200);
    }

    public function save(Request $request)
    {
        $request->validate([
            'desc' => 'required',
            'title' => 'required',
            'duration' => 'required',
            'featured' =>'image',
            'type' =>'required',
            'amount' => 'numeric',
            'venue' => 'required',
            'highlight1' => 'required',
            'highlight2' => 'required',
            'highlight3' => 'required',
            'highlight4' => 'required',
            'highlight5' => 'required',
            'attendee' => 'required|numeric',
            'startdate' => 'required'
        ]);
        $data = [];
         array_push($data,
         $request->highlight1,
         $request->highlight2,
         $request->highlight3,
         $request->highlight4,
         $request->highlight5);

         $programme = new Programme();

        $programme->title = $request->title;
        $programme->overview = $request->desc;
        $programme->duration = $request->duration;
        $programme->key_features = json_encode($data);
        $programme->fee = $request->amount;
        $programme->venue = $request->venue;
        $programme->startdate = $request->startdate;
        $programme->attendee = $request->attendee;
        $programme->type = $request->type;
        $programme->status = 'pending';
        $programme->createdBy = Auth::user()->id;
        $programme->enddate = Carbon::parse($request->startdate)->addDays($request->duration);
        $programme->featured = $request->featured->store('programmes');
        $programme->save();
        return response()->json($programme, 200);
    }

   public function joinRequest(Request $request)
        {
            $user = Auth::user();
            $programme = Programme::findOrFail($request->id);
            WaitingList::create([
                'username' => $user->first_name.' '.$user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'programme_title' => $programme->title,
                'user_id' => $user->id,
                'programme_id' => $programme->id
            ]);
            Mail::to($user->email)->send(new JoinProgramme($programme, $user));
           return response()->json(['status'=>'success'], 200);
        }



    public function waitingApproval(Request $request)
    {
        //Todo: Check if the current user is the owner of event befor approval
        $waiting = WaitingList::findOrFail($request->id);
        $waiting->status = 'approved';
        $waiting->save();
        $programme = Programme::findOrFail($waiting->programme_id);
        $programme->users()->attach($waiting->user_id);
        $user = User::findOrFail($waiting->user_id);
        Mail::to($user->email)->send(new JoinApproved($programme, $user));
        return response()->json(['status'=>'success'], 200);
    }

    public function deleteProgram(Request $request)
    {
        $id = $request->id;
     $program = Programme::findOrFail($id);
     $user = Auth::user();
     if ($user->id === $program->user_id || $user->isAdmin === 1) {
        $program->delete();
        return response()->json(['status'=> 'deleted'], 200);
        }
        return response()->json(['status'=> 'you cannot delete'], 404);
    }
}
