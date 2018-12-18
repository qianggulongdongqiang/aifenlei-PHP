<?php
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\UserScoreLogModel;

class UserScoreController extends AdminBaseController{

    public function index(){
		$param = $this->request->param();
		$condition = [];
		$type = isset($param['type']) ? trim($param['type']) : '' ;
		if($type){
			$condition['action'] = ['like', $type . '%'];
		}
		
		$startTime = empty($param['start_time']) ? '' : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? '' : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $condition['create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $condition['create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $condition['create_time'] = ['<= time', $endTime];
            }
        }
		
		$mobile = isset($param['mobile']) ? trim($param['mobile']) : '' ;
		if($mobile){
			$condition['user_id'] = Db::name('user')->where(['mobile'=>$mobile])->value('id');
		}
		
		$scoreModel = new UserScoreLogModel();
		
		$ctype = ['order'=>'生成订单', 'consume'=>'积分消费', 'change'=>'积分调整', 'score'=>'积分兑换', 'cash'=>'提现', 'invite'=>'邀请'];
		
		$data = $scoreModel
				->where($condition)
				->with('user')
				->order("create_time DESC")
				->paginate(10, false,['query'=>request()->param()]);
				
        $this->assign('data', $data);
		$this->assign('type', $type);
		$this->assign('mobile', $mobile);
		$this->assign('start_time', $startTime ? date('Y-m-d', $startTime) : '');
		$this->assign('end_time', $endTime ? date('Y-m-d', $endTime) : '');
		$this->assign('ctype', $ctype);
        $this->assign('page', $data->render());


        return $this->fetch();
    }
	
	public function add(){
        return $this->fetch();
    }

	public function addPost(){
        if ($this->request->isPost()) {
            $data   = $this->request->param();
			
			if(empty(trim($data['user_mobile']))){
				$this->error('请填写手机号');	
			}

            //通过手机号查询用户信息
			$user = Db::name('user')->where(['mobile'=>$data['user_mobile'], 'user_type'=>2])->find();
			
            if (!$user) {
                $this->error('用户不存在');
            }

			$d['user_id'] = $user['id'];
			$d['create_time'] = time();
			$d['action'] = 'change:' . trim($data['remark']);
			$d['score'] = intval($data['score']);
			
			if($d['score'] == 0){
				$this->error('积分数不能为0');
			}elseif($d['score'] + $user['score'] < 0){
				$this->error('用户积分数不足');
			}

			$result = Db::name('user_score_log')->insert($d);
			
			Db::name('user')->where(['id'=>$user['id']])->setInc('score', $d['score']);

            if($result)	{
				$this->success('添加成功!', url('UserScore/index'));
			}else{
				$this->error('添加失败!');	
			}
        }

    }
}
