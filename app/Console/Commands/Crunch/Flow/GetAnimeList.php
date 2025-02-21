<?php

namespace App\Console\Commands\Crunch\Flow;

use App\Models\Anime;
use App\Services\crunch;
use Exception;
use Illuminate\Console\Command;

class GetAnimeList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crunch:get-anime-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get full anime list';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $offset = 0;
            $limit = 36;
            $crunchService = new Crunch();

            do {
                // Fetch a page of Root CA certificates
                $response = $crunchService->getAnimeList($offset);

                if (isset($response['data'])) {
                    foreach ($response['data'] as $anime) {
//                        dd($anime);
                        Anime::updateOrCreate(
                            [
                                'anime_id' => $anime['id'], // Unique identifier
                            ],
                            [
                                'episode_count' => $anime['series_metadata']['episode_count'] ??  null,
                                'season_count' => $anime['series_metadata']['season_count'] ??  null,
                                'series_launch_year' => $anime['series_metadata']['series_launch_year'],
                                'is_mature' => $anime['series_metadata']['is_mature'] ??  null,
                                'rating_total' => $anime['rating']['total'] ??  null,
                                'rating_average' => $anime['rating']['average'] ??  null,
                                'rating_unit' => 'K',
                                'external_id' => $anime['external_id'] ?? null,
                                'images' => isset($anime['images']) ? json_encode($anime['images']) : null,
                                'slug_title' => $anime['slug_title'] ?? null,
                                'linked_resource_key' => $anime['linked_resource_key'] ?? null,
                                'channel_id' => $anime['channel_id'] ?? null,
                                'description' => $anime['description'] ?? null,
                                'is_new' => $anime['new'] ?? false,
                                'series_metadata' => isset($anime['series_metadata']) ? json_encode($anime['series_metadata']) : null,
                                'rating' => isset($anime['rating']) ? json_encode($anime['rating']) : null,
                                'type' => $anime['type'] ?? 'series',
                                'last_public' => $this->validateDate($anime['last_public']) ? date('Y-m-d H:i:s', strtotime($anime['last_public'])) : null,
                                'title' => $anime['title'] ?? null,
                            ]
                        );
                    }
                }

                $offset += $limit;
            } while ($offset < $response['total']);

            info("All Anime fetched successfully.");
        } catch (Exception $e) {
            $this->error("Error fetching: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    private function validateDate(?string $date): bool
    {
        if (!$date) {
            return false;
        }

        $d = \DateTime::createFromFormat(DATE_ATOM, $date);
        return $d !== false;
    }


}
