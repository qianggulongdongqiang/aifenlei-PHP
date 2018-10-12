<?php
namespace app\portal\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\UserModel;
use PHPExcel_IOFactory;
use PHPExcel;


class PreOrdersController extends AdminBaseController{
    /**
     * 订单列表
     * @adminMenu(
     *     'name'   => '订单列表',
     *     'parent' => 'portal/Orders/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '订单列表',
     *     'param'  => ''
     * )
     */
    public function index(){
		$param = $this->request->param();
		
		$where = [];
		$search = [];
		$sn = empty($param['sn']) ? '' : $param['sn'];
        if (!empty($sn)) {
            $where['order_sn'] = ['like', "%$sn%"];	
        }
		$search['sn'] = $sn;
		
		$startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        if (!empty($startTime)) {
            $where['finished_time'] = ['like', date('Y年m月d日').'%'];	
        }
		$search['startTime'] = $startTime;

		$collecter_id = empty($param['collecter_id']) ? 0 : intval($param['collecter_id']);
		if(!empty($collecter_id)){
			$where['collecter_id'] = $collecter_id;
		}
		$search['collecter_id'] = $collecter_id;
		
		$order_state = isset($param['order_state']) ? intval($param['order_state']) : -1;
		if($order_state != '-1'){
			$where['order_state'] = $order_state;
		}
		$search['order_state'] = $order_state;
		
		
        $data = Db::name('pre_order')->where($where)->order('finished_time desc')->paginate(10, false,['query'=>request()->param()]);
		
		$collecter = Db::name('user')->where('user_type', '3')->field('id, user_nickname')->select();

        $this->assign('data', $data);
        $this->assign('page', $data->render());
		$this->assign('collecter', $collecter);
		$this->assign('search', $search);
        return $this->fetch();
    }
	
	public function cancel(){
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $result = Db::name('pre_order')->where(["order_id" => $id, "order_state" => 10])->setField('order_state', '0');
            if ($result !== false) {
                $this->success("预约订单取消成功！", url("pre_orders/index"));
            } else {
                $this->error('预约订单取消失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }
	
}