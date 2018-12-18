<?php
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\CashModel;

class CashController extends AdminBaseController{

    public function index(){
		$param = $this->request->param();
		$condition = [];
		
		$startTime = empty($param['start_time']) ? '' : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? '' : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $condition['add_time'] = [['>=', $startTime], ['<=', $endTime]];
        } else {
            if (!empty($startTime)) {
                $condition['add_time'] = ['>=', $startTime];
            }
            if (!empty($endTime)) {
                $condition['add_time'] = ['<=', $endTime];
            }
        }
		
		$mobile = isset($param['mobile']) ? trim($param['mobile']) : '' ;
		if($mobile){
			$condition['user_id'] = Db::name('user')->where(['mobile'=>$mobile])->value('id');
		}
		
		$scoreModel = new CashModel();
		
		$data = $scoreModel
				->where($condition)
				->with('user')
				->order("add_time DESC")
				->paginate(10, false,['query'=>request()->param()]);

        $this->assign('data', $data);
		$this->assign('mobile', $mobile);
		$this->assign('start_time', $startTime ? date('Y-m-d', $startTime) : '');
		$this->assign('end_time', $endTime ? date('Y-m-d', $endTime) : '');
        $this->assign('page', $data->render());


        return $this->fetch();
    }
}
