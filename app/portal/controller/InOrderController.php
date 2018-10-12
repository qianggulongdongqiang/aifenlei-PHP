<?php
namespace app\portal\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\UserModel;
use app\portal\model\OrderModel;
use app\portal\model\GoodsCateModel;
use app\portal\model\InOrderModel;


class InOrderController extends AdminBaseController{

    public function index(){
		$param = $this->request->param();
		
		$where = [];
		
		$collecter_id = empty($param['collecter_id']) ? 0 : intval($param['collecter_id']);
		if(!empty($collecter_id)){
			$where['collecter_id'] = $collecter_id;
			
			$startTime = DB::name('in_order')->where(['user_id'=>$collecter_id])->order('start_time desc')->value('end_time');

			if(!$startTime){
				$startTime = DB::name('order')->where(['collecter_id' => $collecter_id, 'is_stock'=>0])->order('finished_time asc')->value('finished_time');
			}else{
				$startTime += 86400;
			}
		}
		
		$startTime   = empty($startTime) ? 0 : $startTime;
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
		if (!empty($startTime) && !empty($endTime)) {
            $where['finished_time'] = [['>= time', $startTime], ['<= time', $endTime + 86400]];
			$this->assign('start_time', date('Y-m-d', $startTime));
			$this->assign('end_time', date('Y-m-d', $endTime));
        } else {
            if (!empty($startTime)) {
                $where['finished_time'] = ['>= time', $startTime];
				$this->assign('start_time', date('Y-m-d', $startTime));
            }else{
				$this->assign('start_time', '');
			}
            if (!empty($endTime)) {
                $where['finished_time'] = ['<= time', $endTime + 86400];
				$this->assign('end_time', date('Y-m-d', $endTime));
            }else{
				$this->assign('end_time', date('Y-m-d'));
			}
        }

		$data = [];
		
		$total = 0;
		
		if($collecter_id && $startTime && $endTime){
			$where['is_stock'] = 0;
			
			//获取订单信息
			$orders = DB::name('order')->where($where)->select();
			
			$goods = [];
			
			foreach(DB::name('goods_cate')->select() as $gl){
				$goods[$gl['id']] = $gl;
			}
			
			if($orders){
				foreach($orders as $v){
					foreach(json_decode($v['goods'], true) as $g){
						if(isset($data[$g['id']])){
							$data[$g['id']]['num'] += round($g['num'] , 2);
						}else{
							$data[$g['id']] = [
										'id' => $g['id'],
										'num' => round($g['num'] , 2),
										'name' => $goods[$g['id']]['name'],
										'price' => $goods[$g['id']]['settlement_in_price'],
										];
						}
						
						$total += round($g['num'] , 2) * $data[$g['id']]['price'];
					}
				}
			}
		}
		$this->assign('data', array_values($data));
		$this->assign('total', $total);	
		
		$collecter = Db::name('user')->where('user_type', '3')->field('id, user_nickname')->select();

		$this->assign('collecter', $collecter);
		$this->assign('collecter_id', $collecter_id);
        return $this->fetch();
    }
	
	public function add(){
        $param = $this->request->param();
		
		$d['user_id'] = $param['user_id'];
		$d['start_time'] = strtotime($param['start_time']);
		$d['end_time'] = strtotime($param['end_time']);

		$ids = $param['id'];
		
		$where = [];
		
		$collecter_id = empty($param['user_id']) ? 0 : intval($param['user_id']);
		if(!empty($collecter_id)){
			$where['collecter_id'] = $collecter_id;
		}
		
		$startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
		if (!empty($startTime) && !empty($endTime)) {
            $where['finished_time'] = [['>= time', $startTime], ['<= time', $endTime + 86400]];
        } else {
            if (!empty($startTime)) {
                $where['finished_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['finished_time'] = ['<= time', $endTime + 86400];
            }
        }

		$data = [];			//理论数组
		$act_data = [];		//实际数组
		$order_update = [];	//订单更新数据
		$total = 0;			//理论总数
		$act_total = 0;		//实际总数
		
		if($collecter_id && $startTime && $endTime){
			$where['is_stock'] = 0;
		
			//获取订单信息
			$orders = DB::name('order')->where($where)->select();
			
			//品类列表
			$goods = [];
			foreach(DB::name('goods_cate')->select() as $gl){
				$goods[$gl['id']] = $gl;
			}
			
			if($orders){
				
				//处理订单理论数据
				foreach($orders as $v){
					foreach(json_decode($v['goods'], true) as $g){
						if(isset($data[$g['id']])){
							$data[$g['id']]['num'] += round($g['num'] , 2);
						}else{
							$data[$g['id']] = [
										'goods_id' => $g['id'],
										'num' => round($g['num'] , 2),
										'type' => 1,
										'price' => $goods[$g['id']]['settlement_in_price'],
										];
						}
						
						$total += round($g['num'] , 2) * $data[$g['id']]['price'];
					}
					
					$order_update[] = [
										'order_id' => $v['order_id'],
										'is_stock' => 1
									];
				}
				
				//处理订单实际数据
				foreach($param['id'] as $k=>$v){
					if(isset($goods[$k])){
						$act_data[$k] = [
											'goods_id' => $k,
											'num' => round($v , 2),
											'type' => 2,
											'price' => $goods[$k]['settlement_in_price'],
										];
						$act_total += $act_data[$k]['num'] * $act_data[$k]['price'];			
						
					}
				}
				
				//合并
				$data = array_merge($data, $act_data);
	
				$d['amount'] = $total;
				$d['actual_amount'] = $act_total;
				
				$orderModel = new OrderModel();
				$goodsCateModel = new GoodsCateModel();
				
				/* Db::startTrans(); //开启事务
                $transStatus = false;
                try {
					//生成入库单
                    $insertID = DB::name('in_order')->insertID($d);
					
					foreach($data as $k=>$v){
						$data[$k]['order_id'] = $insertID; 
					}
					DB::name('in_order_detail')->insertAll($data);
					
					//更新库存
					DB::name('goods_cate')->saveAll($update);
					
					//更新订单状态
					DB::name('order')->saveAll($order_update);

                    $transStatus = true;
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {

                    // 回滚事务
                    Db::rollback();
                } */
				
				
				//生成入库单
				if($insertID = DB::name('in_order')->insertGetId($d)){
					foreach($data as $k=>$v){
						$data[$k]['order_id'] = $insertID; 
					}
					DB::name('in_order_detail')->insertAll($data);
					
					//更新库存
					foreach($act_data as $v){
						$goodsCateModel->where(['id'=>$v['goods_id']])->setInc('stock', $v['num']);
					}
					
					//更新订单状态
					$orderModel->saveAll($order_update);
					
					$this->success("提交成功", '');
					
				}else{
					$this->error("生成失败！", '');
				}	
			}else{
				$this->error("数据错误！", '');
			}
		}else{
			$this->error("数据错误！", '');
		}
    }
	
	/**
	 *	结算历史
	 */
	public function history(){
		$inOrderModel = new InOrderModel();
		
		$param = $this->request->param();
		
		$where = [];
		
		$collecter_id = empty($param['collecter_id']) ? 0 : intval($param['collecter_id']);
		if(!empty($collecter_id)){
			$where['user_id'] = $collecter_id;
		}
		
		$startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);

		if (!empty($startTime)) {
			$where['start_time'] = ['>= time', $startTime];
			$this->assign('start_time', date('Y-m-d', $startTime));
		}else{
			$this->assign('start_time', '');
		}
		if (!empty($endTime)) {
			$where['end_time'] = ['<= time', $endTime + 86400];
			$this->assign('end_time', date('Y-m-d', $endTime));
		}else{
			$this->assign('end_time', date('Y-m-d'));
		}


		$data = [];
		
		if($collecter_id){

			$goods = [];
			
			foreach(DB::name('goods_cate')->select() as $gl){
				$goods[$gl['id']] = $gl;
			}
			$this->assign('goods', $goods);
			
			$data = $inOrderModel->where($where)->select()->toArray();
			if($data){
				foreach($data as $k=>$v){
					$data[$k]['g1'] = DB::name('InOrderDetail')->where(['type'=>1, 'order_id'=>$v['id']])->select();
					$data[$k]['g2'] = DB::name('InOrderDetail')->where(['type'=>2, 'order_id'=>$v['id']])->select();
				}
			}
		}
		$this->assign('data', $data);
		
		
		$collecter = Db::name('user')->where('user_type', '3')->field('id, user_nickname')->select();

		$this->assign('collecter', $collecter);
		$this->assign('collecter_id', $collecter_id);
        return $this->fetch();
    }
}