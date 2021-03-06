<?php


namespace App\Http\Controllers;


use App\Mail\ContactForm;
use App\Models\User;
use App\Models\WebContact;
use Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use stdClass;

class UserController extends Controller
{
    public function redirectToProvider() {
        return Socialite::driver('google')->redirect();
    }
    public function handleProviderCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login');
        }
        $existingUser = User::where('email', $user->email)->first();
        if($existingUser){
            auth()->login($existingUser, true);
        } else {
            $newUser                  = new User;
            $newUser->name            = $user->name;
            $newUser->email           = $user->email;
            $newUser->google_id       = $user->id;
            $newUser->avatar          = $user->avatar;
            $newUser->avatar_original = $user->avatar_original;
            $newUser->save();
            auth()->login($newUser, true);
        }
        $user = Auth::user();
        $token =  $user->createToken('MyApp')-> accessToken;
        return redirect()->to(env('APP_URL').'/login?code='.$user->google_id.'&token='.$token);
    }
    public function get_posts(Request $request) {
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        $must = ['match_all' => new stdClass];
        if($request->tags)
        {
            $must = [];
            foreach($request->tags as  $tag){
                array_push($must,['term'=>['tags'=>$tag]]);
            }
        }
        if($request->blood_group)
        {
            if(!is_array($must))
                $must =[];
            array_push($must,['term'=>['blood_group.keyword'=>$request->blood_group]]);

        }
        if($request->age_start||$request->age_end){
            if(!is_array($must))
                $must =[];
            $start_age = 0;
            $end_age = 200;
            if($request->age_start)
                $start_age= $request->age_start;
            if($request->age_end)
                $end_age = $request->age_end;
            array_push($must,['range'=>['age'=>['gte'=>$start_age,'lte'=>$end_age,'boost'=>2.0]]]);

        }
        if($request->positive_date) {
            if (!is_array($must))
                $must = [];
            array_push($must,['range'=>['positive_date'=>['lte'=>$request->positive_date,'boost'=>2.0]]]);
        }
        if($request->negative_date) {
            if (!is_array($must))
                $must = [];
            array_push($must,['range'=>['negative_date'=>['lte'=>$request->negative_date,'boost'=>2.0]]]);
        }
        if($request->weight_start||$request->weight_end){
            if(!is_array($must))
                $must =[];
            $start_weight = 0;
            $end_weight = 500;
            if($request->weight_start)
                $start_weight= $request->weight_start;
            if($request->weight_end)
                $end_weight = $request->weight_end;
            array_push($must,['range'=>['weight'=>['gte'=>$start_weight,'lte'=>$end_weight,'boost'=>2.0]]]);

        }
        if($request->sort){
            $sort = ['date'=>'desc'];
        }else{
            $sort = [
                [
                    '_geo_distance' => [
                        'pin.location' => [floatval($request->lat), floatval($request->lng)],
                        "order" => "asc",
                        "unit" => "km",
                        "mode" => "min",
                        "distance_type" => "arc",
                        "ignore_unmapped" => true
                    ]
                ]
            ];
        }

        $body = [
            'must' => $must
        ];
        if($request->lat&&$request->lng){
            $filter = [
                'geo_distance' => ["distance" => $request->distance."km", "pin.location" => [
                    "lat" => floatval($request->lat),
                    "lon" => floatval($request->lng)
                ]]
            ];
            $body['filter'] = $filter;
        }
        $params = [
            'index' => 'posts',
            'body' => [
                'query' => [
                    'bool' => $body
                ],
                'sort' => $sort
            ]
        ];

        $results = $client->search($params);

        $ans = array_map(function ($pst){
            $post = ['name'=>$pst['_source']['name'],'content'=>$pst['_source']['content']];
            if(isset($pst['_source']['formatted_address']))
                $post['formatted_address'] = $pst['_source']['formatted_address'];
            else
            $post['formatted_address'] = 'No Adress';
            if(isset($pst['_source']['avatar']))
                $post['avatar'] = $pst['_source']['avatar'];
            else
                $post['avatar'] = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTVIr6pSB3YonR9a0c7WU0iMWEX8ggImki9OLNnLPHYn590JxYkWNaNWB1h4vch9AJcBec&usqp=CAU';
            if(isset($pst['_source']['tags']))
                $post['tags'] = $pst['_source']['tags'];
            if(isset($pst['_source']['age']))
                $post['age'] = $pst['_source']['age'];
            if(isset($pst['_source']['user_id']))
                $post['user_id'] = $pst['_source']['user_id'];
            if(isset($pst['_source']['weight']))
                $post['weight'] = $pst['_source']['weight'];
            if(isset($pst['_source']['blood_group']))
                $post['blood_group'] = $pst['_source']['blood_group'];
            if(isset($pst['_source']['gender']))
                $post['gender'] = $pst['_source']['gender'];
            if(isset($pst['_source']['public_profile']))
                $post['public_profile'] = $pst['_source']['public_profile'];
            if(isset($pst['_source']['contact']))
                $post['contact'] = $pst['_source']['contact'];
            if(isset($pst['_source']['positive_date']))
                $post['positive_date'] = $pst['_source']['positive_date'];
            if(isset($pst['_source']['negative_date']))
                $post['negative_date'] = $pst['_source']['negative_date'];
            return $post;
        },$results['hits']['hits']);

        return $ans;

    }
    public function my_posts(Request $request)
    {
        $user = Auth::user();
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        $must = [['term'=>['user_id'=>$user->id]]];
        $sort = ['date'=>'desc'];
        $body = [
            'must' => $must
        ];
        $params = [
            'index' => 'posts',
            'body' => [
                'query' => [
                    'bool' => $body
                ],
                'sort' => $sort
            ]
        ];

        $results = $client->search($params);
        $ans = array_map(function ($pst){
            $post = ['name'=>$pst['_source']['name'],'content'=>$pst['_source']['content']];
            if(isset($pst['_source']['formatted_address']))
                $post['formatted_address'] = $pst['_source']['formatted_address'];
            else
                $post['formatted_address'] = 'No Adress';
            if(isset($pst['_source']['avatar']))
                $post['avatar'] = $pst['_source']['avatar'];
            else
                $post['avatar'] = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTVIr6pSB3YonR9a0c7WU0iMWEX8ggImki9OLNnLPHYn590JxYkWNaNWB1h4vch9AJcBec&usqp=CAU';
            if(isset($pst['_source']['tags']))
                $post['tags'] = $pst['_source']['tags'];
            if(isset($pst['_source']['age']))
                $post['age'] = $pst['_source']['age'];
            if(isset($pst['_source']['weight']))
                $post['weight'] = $pst['_source']['weight'];
            if(isset($pst['_source']['gender']))
                $post['gender'] = $pst['_source']['gender'];
            if(isset($pst['_source']['public_profile']))
                $post['public_profile'] = $pst['_source']['public_profile'];
            if(isset($pst['_source']['contact']))
                $post['contact'] = $pst['_source']['contact'];
            if(isset($pst['_source']['positive_date']))
                $post['positive_date'] = $pst['_source']['positive_date'];
            if(isset($pst['_source']['negative_date']))
                $post['negative_date'] = $pst['_source']['negative_date'];
            $post['id'] = $pst['_id'];
            return $post;
        },$results['hits']['hits']);

        return $ans;
    }
    public function delete_post(Request $request,$id) {
        $user = Auth::user();
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        $params = [
            'index' => 'posts',
            'id'    => $id
        ];

// Delete doc at /my_index/_doc_/my_id
        $response = $client->delete($params);

        return ['msg'=>'done'];
    }
    public function create_post(Request $request) {
        $user = Auth::user();
        $g_client = new Client;
        $response = $g_client->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'form_params' =>
                    [
                        'secret' => config('services.recaptcha.secret'),
                        'response' => $request->captcha
                    ]
            ]
        );
        $body = json_decode((string)$response->getBody());
        if(!$body->success){
            return  ['msg'=>'failed','success'=>false];
        }
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        $body = [
            'name' => $user->name,
            'avatar' => $user->avatar,
            'content' => $request->post_content,
            'formatted_address' => $request->formatted_address,
            'user_id' => $user->id,
            'date' => time(),
            "pin" => [
                "location" => [
                    "lat" => $request->lat,
                    "lon" => $request->lng
                ]
            ]
        ];
        if($request->tags)
            $body['tags'] = $request->tags;
        if($request->blood_group)
            $body['blood_group'] = $request->blood_group;
        if($request->age)
            $body['age'] = intval($request->age);
        if($request->weight)
            $body['weight'] = intval($request->weight);
        if($request->gender)
            $body['gender'] = $request->gender;
        if(isset($pst['_source']['blood_group']))
            $post['blood_group'] = $pst['_source']['blood_group'];
        if($request->public_profile)
            $body['public_profile'] = $request->public_profile;
        if($request->contact)
            $body['contact'] = $request->contact;
        if($request->positive_date)
            $body['positive_date'] = $request->positive_date;
        if($request->negative_date)
            $body['negative_date'] = $request->negative_date;

        $params = [
            'index' => 'posts',
            'body' => $body
        ];


        $response = $client->index($params);

        return ['msg'=>'done','success'=>true];

    }
    public function send_email(){
        $user = User::find(1);
        Mail::to($user)->send(new ContactForm());
        return ['msg'=>'done'];
    }
    public function send_message(Request $request){
        $client = new Client;
        $response = $client->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'form_params' =>
                    [
                        'secret' => config('services.recaptcha.secret'),
                        'response' => $request->captcha
                    ]
            ]
        );
        $body = json_decode((string)$response->getBody());
        if($body->success){
            $web_contact = new WebContact();
            $web_contact->name = $request->name;
            $web_contact->email = $request->email;
            $web_contact->message = $request->message;
            $web_contact->save();
            $user = new User();
            $user->email = env('CONTACT_EMAIL');
            Mail::to($user)->send(new ContactForm($web_contact));
            return ['msg'=>'done'];

        }
        return ['msg'=>'failed','body'=>$body];
    }
    public function send_message_user(Request $request){
        $user = Auth::user();
        $client = new Client;
        $response = $client->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'form_params' =>
                    [
                        'secret' => config('services.recaptcha.secret'),
                        'response' => $request->captcha
                    ]
            ]
        );
        $body = json_decode((string)$response->getBody());
        if($body->success){
            $other = User::find($request->user_id);
            $web_contact = new WebContact();
            $web_contact->name = $user->name;
            $web_contact->email = $user->email;
            $web_contact->message = $request->message;
            $web_contact->save();

            Mail::to($other)->send(new ContactForm($web_contact));
            return ['msg'=>'done'];

        }
        return ['msg'=>'failed','body'=>$body];
    }
}
