<?php
namespace app\api\jobs;

use cmf\controller\RestBaseController;
use app\api\model\UserModel;
use app\api\model\GoodsCateModel;
use app\api\model\OrderModel;
use app\api\model\PreOrderModel;
use app\api\model\WxModel;
use think\Validate;
use think\Db;
use think\queue\Job;

class WxJob{
	
	/**
	 *	提现队列
	 */
	public function cash(Job $job, $data){
		
		$user = $data['user'];
		unset($data['user']);
		
		$score = intval($data['score']);
		$rate = 100;
		
		$param['openid'] 	= $user['openid'];
		$param['userName'] 	= $data['name'];
		$param['Sn'] 		= makeSn($user['id']);
		$param['Fee'] 		= $score / $rate * 100;
		
		if($user['score'] < 2000){
			$job->delete();
			return ['code' => 0, 'msg' => '用户积分不能少于2000!'];
		}
		
		//查询积分
		if($user['score'] < $score){
			$job->delete();
			return ['code' => 0, 'msg' => '积分不足!'];
		}
		
		Db::startTrans(); //开启事务
		$transStatus = false;
		try {
			//扣除积分
			Db::name('user')->where(['id'=>$user['id']])->setDec('score', $score);
			
			//增加提现记录
			Db::name('cash')->insert([
													'sn' => $param['Sn'],
													'user_id'=>$user['id'],
													'amount'=>$param['Fee'],
													'name'=>$param['userName'],
													'add_time'=>time(),
												]);
												
			//增加用户积分日志
			Db::name('user_score_log')->insert([
													'user_id'=>$user['id'],
													'create_time'=>time(),
													'action'=>'cash:'.$param['Sn'],
													'score'=>'-' .$param['Fee']
												]);

			$transStatus = true;
			// 提交事务
			Db::commit();
		} catch (\Exception $e) {

			// 回滚事务
			Db::rollback();
		}
		
		if($transStatus){
			$wxModel 	= new WxModel();
			$re = $wxModel->transfers($param);
			
			//微信接口成功，更新数据
			if($re['result_code'] == 'SUCCESS'){
				$condition['sn'] = $param['Sn'];
				
				Db::name('cash')->where($condition)->update([
																'payment_state'=>1,
																'payment_time'=>time(),
																'trade_no' => $re['payment_no'],
															]);
				$outData['score'] = $score;
				$outData['money'] = number_format($score / $rate, 2);
				$outData['time'] = date('Y-m-d H:i:s');
				
				//修改用户姓名
				Db::name('user')->where(['id'=>$user['id']])->setfield('user_nickname', $param['userName']);
				
				$job->delete();
				return ['code' => 1, 'msg' => '提现成功!', 'data'=>$outData];
			}else{
				//失败，还原数据
				Db::name('user')->where(['id'=>$user['id']])->setInc('score', $score);
				
				Db::name('cash')->where(['sn'=>$param['Sn']])->delete();
													
				Db::name('user_score_log')->where(['action'=>['like', '%'.$param['Sn']]])->delete();
				
				$msg = isset($re['err_code_des']) ? $re['err_code_des'] : '';
				
				
				$this->_log('提现失败!' . $msg);
				$job->delete();
				return ['code' => 0, 'msg' => '提现失败!' . $msg];
			
			}
		}else{
			$job->delete();
			return ['code' => 0, 'msg' => '提现失败!'];
		}
		
		
		$job->delete();
	}
	
	private function _log($data){
		$fp = fopen(CMF_ROOT . 'jobs.txt', 'a+');
		fwrite($fp, var_export($data, true) . ' - ' . date('Y-m-d H:i:s') . "\r\n");
		fclose($fp);
	}
}
