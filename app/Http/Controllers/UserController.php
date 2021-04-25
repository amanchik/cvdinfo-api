<?php


namespace App\Http\Controllers;


use App\Models\User;
use Elasticsearch\ClientBuilder;
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
        return redirect()->to('http://localhost:4200/login?code='.$user->google_id.'&token='.$token);
    }
    public function get_posts() {
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        $params = [
            'index' => 'posts',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => ['match_all' => new stdClass],
                        'filter' => [
                            'geo_distance' => ["distance" => "200km", "pin.location" => [
                                "lat" => 40,
                                "lon" => -70
                            ]]
                        ]
                    ],

                ]
            ]
        ];

        $results = $client->search($params);

        return $results['hits']['hits'];

    }
}
