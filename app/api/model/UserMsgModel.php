<?php
namespace app\api\model;

use think\Db;
use think\Model;

class UserMsgModel extends Model{
	
	
	
	/**
	 * 推送消息
	 */
	public function push($registration_id, $title = 'hello', $info = [], $role = 'c'){
		vendor('jpush.autoload');
		
		$app_key = 'c87c1725facbf99fcfc62493';
		$master_secret = '9f0dc2190a4ba0514f2b511a';
		
		$config = [
					'c' => [
								'key'=>'37f88a2335e5a158f7ab0d86',
								'secret'=>'7e6dff3a9986e7f653cd2c5f'
							],
					'm' => [
								'key'=>'c87c1725facbf99fcfc62493',
								'secret'=>'9f0dc2190a4ba0514f2b511a'
							],
				];
				
		if(!in_array($role, ['c', 'm'])){
			$role = 'c';
		}
		


		$client = new \JPush\Client($config[$role]['key'], $config[$role]['secret']);

		
		if($info){
			$push_payload = $client->push()
			->setPlatform('all')
			->addRegistrationId($registration_id)
			->setNotificationAlert($title)
			->iosNotification($title, ['extras' => $info])
			->androidNotification($title, ['extras' => $info])
			->addWinPhoneNotification($title, null, null, ['extras' => $info]);
		}else{
			$push_payload = $client->push()
			->setPlatform('all')
			->addRegistrationId($registration_id)
			->setNotificationAlert($title);
		}
			
			
		@$response = $push_payload->send();
		return $response;

	}
}
