<?php


namespace App\Http\Controllers;


use App\Models\User;
use Elasticsearch\ClientBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $params = [
            'index' => 'posts',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => $must,
                        'filter' => [
                            'geo_distance' => ["distance" => $request->distance."km", "pin.location" => [
                                "lat" => floatval($request->lat),
                                "lon" => floatval($request->lng)
                            ]]
                        ]
                    ],

                ],
                'sort' => [
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
                ]
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
            return $post;
        },$results['hits']['hits']);

        return $ans;

    }
    public function create_post(Request $request) {
        $user = Auth::user();
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
            $body['age'] = $request->age;
        if($request->weight)
            $body['weight'] = $request->weight;

        $params = [
            'index' => 'posts',
            'body' => $body
        ];


        $response = $client->index($params);

        return ['msg'=>'done'];

    }
}
