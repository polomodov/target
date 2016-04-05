<?php  
namespace Mobio\Target;


class MyTargetApi extends \Mobio\Target\Api
{

	/**
	 * url to get the clients list
	 */
	const CLIENTS_URL = "/api/v1/clients.json";

	/**
	 * url to get the campaigns list
	 */
	const CAMPAIGNS_URL = "/users/%s/api/v1/campaigns.json?fields=id";

	/**
	 * url to get the campaigns statitics list
	 */
	const CAMPAIGNS_STATS_URL = "/users/%s/api/v1/statistics/campaigns/%s/day/%s-%s.json";


	/**
	 * list of the clients 
	 * @return array
	 */
	public function getClients()
	{
		return $this->request(self::CLIENTS_URL)->parse();
	}

	/**
	 * list of the user`s campaigns
	 * @param  string $user user email
	 * @return array
	 */
	public function getUserCampaignsList($user)
	{
		return $this->request(sprintf(self::CAMPAIGNS_URL, $user))->parse();
	}

	/**
	 * list of the user`s campaigns statistics for the period
	 * @param  string $user      user email
	 * @param  string $dateStart start of the period
	 * @param  string $dateEnd   end of the period
	 * @return array
	 */
	public function getUserCampaigns($user, $dateStart, $dateEnd)
	{
		$campaigns = $this->getUserCampaignsList($user);
		if (!empty($campaigns)) {
			$campaigns = array_map(function($item) {
				return $item->id;
			}, $campaigns);

			$campaignsStr 	= implode(';', $campaigns);
			$dateStart		= date('d.m.Y', strtotime($dateStart));
			$dateEnd		= date('d.m.Y', strtotime($dateEnd));

			return $this->request(sprintf(self::CAMPAIGNS_STATS_URL, $user, $campaignsStr, $dateStart, $dateEnd))->parse();
		} else {
			return [];
		}
	}
}
