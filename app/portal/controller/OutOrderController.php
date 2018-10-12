<?php
namespace app\portal\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\UserModel;
use app\portal\model\OrderModel;
use app\portal\model\GoodsCateModel;


class OutOrderController extends AdminBaseController{

    public function index(){
		$where['parent_id'] = ['neq', 0];
		$where['stock'] = ['gt', 0];
		$data = DB::name('goods_cate')->where($where)->select()->toArray();
		$total = 0;
		
		foreach($data as $k=>$v){
			$data[$k]['total'] = $v['stock'] * $v['settlement_out_price'];
			$total += $data[$k]['total'];
		}

		$this->assign('data', $data);
		$this->assign('total', $total);
        return $this->fetch();
    }
	
	public function add(){
        $param = $this->request->param();

		$ids = $param['id'];

		$total = 0;			
		
		$where['parent_id'] = ['neq', 0];
		$data = DB::name('goods_cate')->where($where)->select()->toArray();
		
		$goods = [];
			
		foreach($data as $gl){
			$goods[$gl['id']] = $gl;
		}
				
		//处理数据
		foreach($param['id'] as $k=>$v){
			if(isset($goods[$k]) && round($v , 2) > 0 && ($goods[$k]['stock'] >= round($v , 2))){
				$act_data[$k] = [
									'goods_id' => $k,
									'num' => round($v , 2),
									'price' => $goods[$k]['settlement_out_price'],
								];
				$total += $act_data[$k]['num'] * $act_data[$k]['price'];			
				
			}
		}
				
		$d['amount'] = $total;
		$d['add_time'] = time();
				
	
		//生成出库单
		if($insertID = DB::name('out_order')->insertGetId($d)){
			foreach($act_data as $k=>$v){
				$act_data[$k]['order_id'] = $insertID; 
			}
			DB::name('out_order_detail')->insertAll($act_data);
			
			//更新库存
			foreach($act_data as $v){
				DB::name('goods_cate')->where(['id'=>$v['goods_id']])->setDec('stock', $v['num']);
			}
			
			$this->success("出库成功", '');
			
		}else{
			$this->error("出库失败！", '');
		}	

    }
	
	/**
	 *	结算历史
	 */
	public function history(){
		
		$param = $this->request->param();
		
		$where = [];
		
		$startTime = empty($param['start_time']) ? strtotime(date('Y-m-d')) : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? strtotime(date('Y-m-d')) : strtotime($param['end_time']);
		if (!empty($startTime) && !empty($endTime)) {
            $where['add_time'] = [['>= time', $startTime], ['<= time', $endTime + 86400]];
			$this->assign('start_time', date('Y-m-d', $startTime));
			$this->assign('end_time', date('Y-m-d', $endTime));
        } else {
            if (!empty($startTime)) {
                $where['add_time'] = ['>= time', $startTime];
				$this->assign('start_time', date('Y-m-d', $startTime));
            }else{
				$this->assign('start_time', date('Y-m-d'));
			}
            if (!empty($endTime)) {
                $where['add_time'] = ['<= time', $endTime + 86400];
				$this->assign('end_time', date('Y-m-d', $endTime));
            }else{
				$this->assign('end_time', date('Y-m-d'));
			}
        }


		$data = [];
		$goods = [];
		
		foreach(DB::name('goods_cate')->select() as $gl){
			$goods[$gl['id']] = $gl;
		}
		$this->assign('goods', $goods);
		
		$data = DB::name('out_order')
						->where($where)
						->order('add_time desc')
						->paginate(10, false,['query'=>request()->param()]);
		$this->assign('page', $data->render());
		$data = $data->toArray();
		if($data){
			foreach($data['data'] as $k=>$v){
				$data['data'][$k]['g'] = DB::name('OutOrderDetail')->where(['order_id'=>$v['id']])->select();
			}
		}

		$this->assign($data);
		
        return $this->fetch();
    }
}