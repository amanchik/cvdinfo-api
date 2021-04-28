<?php

namespace App\Console\Commands;

use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;

class CreateElasticMappings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:mappings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $hosts = [
            env('ELASTIC_HOST'),         // IP + Port
        ];
        $client = ClientBuilder::create()           // Instantiate a new ClientBuilder
        ->setHosts($hosts)      // Set the hosts
        ->build();
        try{
            $params = ['index' => 'posts'];
            $response = $client->indices()->delete($params);

            print_r($response);
        }catch (\Exception  $exception){
            print_r($exception);
        }

        $params = [
            'index' => 'posts',
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2
                ],
                'mappings' => [
                    '_source' => [
                        'enabled' => true
                    ],
                    'properties' => [
                        'date' => [
                            'type' => 'date'
                        ],
                        'age' => [
                            'type' => 'integer'
                        ],
                        'weight' => [
                            'type' => 'integer'
                        ],
                        'pin' => [
                            "properties" => [
                                "location"=> [
                                    "type"=> "geo_point"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];


// Create the index with mappings and settings now
        $response = $client->indices()->create($params);
        print_r($response);
        return 0;
    }
}
