<?php  
namespace Mobio\Target;


class MyTargetApi extends \Mobio\Target\Api
{
    /**
     * Url to get the clients list
     */
    const CLIENTS_URL = '/api/v1/clients.json';

    /**
     * Url to get the campaigns list
     */
    const CAMPAIGNS_URL = '/users/%s/api/v1/campaigns.json?fields=id';

    /**
     * Url to get the campaigns statitics list
     */
    const CAMPAIGNS_STATS_URL = '/users/%s/api/v1/statistics/campaigns/%s/day/%s-%s.json';

    /**
     * List of the clients 
     * @return array
     */
    public function getClients()
    {
        return $this->request(self::CLIENTS_URL)->parse();
    }

    /**
     * List of the user`s campaigns
     * @param  string $username
     * @return array
     */
    public function getUserCampaignsList($username)
    {
        return $this->request(sprintf(self::CAMPAIGNS_URL, $username))->parse();
    }

    /**
     * List of the user`s campaigns statistics for the period
     * @param  string $username
     * @param  string $dateStart start of the period
     * @param  string $dateEnd   end of the period
     * @return array
     */
    public function getUserCampaigns($username, $dateStart, $dateEnd)
    {
        $campaigns = $this->getUserCampaignsList($username);

        if (!empty($campaigns)) {
            $campaigns = array_map(function($item) {
                return $item->id;
            }, $campaigns);

            $campaignsStr 	= implode(';', $campaigns);
            $dateStart		= date('d.m.Y', strtotime($dateStart));
            $dateEnd		= date('d.m.Y', strtotime($dateEnd));
            
            return $this->request(
                sprintf(self::CAMPAIGNS_STATS_URL, $username, $campaignsStr, $dateStart, $dateEnd)
            )->parse();
        } else {
            return [];
        }
    }
}
