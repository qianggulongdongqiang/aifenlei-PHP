<?php
namespace app\portal\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\UserModel;
use PHPExcel_IOFactory;
use PHPExcel;


class OrdersController extends AdminBaseController{
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
		$sn = empty($param['sn']) ? '' : $param['sn'];
        if (!empty($sn)) {
            $where['order_sn'] = ['like', "%$sn%"];
        }
		$this->assign('sn', $sn);
		
		$mobile = empty($param['mobile']) ? '' : $param['mobile'];
        if (!empty($mobile)) {
            $where['buyer_phone'] = ['like', "%$mobile%"];
        }
		$this->assign('mobile', $mobile);
		
		$startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $where['add_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['add_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['add_time'] = ['<= time', $endTime];
            }
        }
		
		$collecter_id = empty($param['collecter_id']) ? 0 : intval($param['collecter_id']);
		if(!empty($collecter_id)){
			$where['collecter_id'] = $collecter_id;
		}
		
		if(!empty($param['explode'])){	//导出
			Vendor('PHPExcel.PHPExcel');//调用类库,路径是基于vendor文件夹的
			Vendor('PHPExcel.PHPExcel.Worksheet.Drawing');
			Vendor('PHPExcel.PHPExcel.Writer.Excel2007');
			$objExcel = new \PHPExcel();
			$objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
			$objActSheet = $objExcel->getActiveSheet();
			$objActSheet->setTitle("订单数据"); //给当前活动sheet设置名称
			
			$key = ord("A");
			$letter =explode(',',"A,B,C,D,E,F,G,H,I");
			$arrHeader =  array('日期','订单号','资源种类','重量（kg）/ 数量（个）','兑换积分','回收员','住户','地址', '手机号');
			//填充表头信息
			$lenth =  count($arrHeader);
			for($i = 0;$i < $lenth;$i++) {
				$objActSheet->setCellValue("$letter[$i]1","$arrHeader[$i]");
			};
			
			$data = Db::name('order')->where($where)->order('add_time desc')->select()->toArray();
			$k = 1;
			//填充表格信息
			foreach($data as $key=>$v){
				$_goods = json_decode($v['goods'], true);
				sort($_goods);

				if($_goods){
					foreach($_goods as $gk=>$g){
						$k += 1;
						if($gk == 0){
							$objActSheet->setCellValue('A'.$k, date('Y-m-d H:i',$v['add_time']));
							$objActSheet->setCellValue('B'.$k, $v['order_sn']);
							$objActSheet->setCellValue('C'.$k, $g['name']);
							$objActSheet->setCellValue('D'.$k, $g['num']);
							$objActSheet->setCellValue('E'.$k, $v['points_number']);
							$objActSheet->setCellValue('F'.$k, $v['collecter_name']);
							$objActSheet->setCellValue('G'.$k, $v['buyer_name']);
							$objActSheet->setCellValue('H'.$k, $v['buyer_addr']);
							$objActSheet->setCellValue('I'.$k, $v['buyer_phone']);	
						}else{
							$objActSheet->setCellValue('A'.$k, '');
							$objActSheet->setCellValue('B'.$k, '');
							$objActSheet->setCellValue('C'.$k, $g['name']);
							$objActSheet->setCellValue('D'.$k, $g['num']);
							$objActSheet->setCellValue('E'.$k, '');
							$objActSheet->setCellValue('F'.$k, '');
							$objActSheet->setCellValue('G'.$k, '');
							$objActSheet->setCellValue('H'.$k, '');
							$objActSheet->setCellValue('I'.$k, ''); 
						}
							
					}
				}else{
					$k += 1;
					$objActSheet->setCellValue('A'.$k, date('Y-m-d H:i',$v['add_time']));
					$objActSheet->setCellValue('B'.$k, $v['order_sn']);
					$objActSheet->setCellValue('C'.$k, '');
					$objActSheet->setCellValue('D'.$k, '');
					$objActSheet->setCellValue('E'.$k, $v['points_number']);
					$objActSheet->setCellValue('F'.$k, $v['collecter_name']);
					$objActSheet->setCellValue('G'.$k, $v['buyer_name']);
					$objActSheet->setCellValue('H'.$k, $v['buyer_addr']);
					$objActSheet->setCellValue('I'.$k, $v['buyer_phone']);
				}

			}
			
			//设置表格的宽度
			$objActSheet->getColumnDimension('A')->setWidth(20);
			$objActSheet->getColumnDimension('B')->setWidth(20);
			$objActSheet->getColumnDimension('C')->setWidth(20);
			$objActSheet->getColumnDimension('D')->setWidth(20);
			$objActSheet->getColumnDimension('E')->setWidth(20);
			$objActSheet->getColumnDimension('F')->setWidth(20);
			$objActSheet->getColumnDimension('G')->setWidth(20);
			$objActSheet->getColumnDimension('H')->setWidth(20);
			$objActSheet->getColumnDimension('I')->setWidth(20);
			
			//自动换行
			$objActSheet->getStyle('C2:C' . (count($data) + 2))->getAlignment()->setWrapText(TRUE);
			$objActSheet->getStyle('D2:D' . (count($data) + 2))->getAlignment()->setWrapText(TRUE);
			
			$outfile = "订单信息".date("Y-m-d").".xls";
			ob_end_clean();
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header('Content-Disposition:inline;filename="'.$outfile.'"');
			header("Content-Transfer-Encoding: binary");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: no-cache");
			$objWriter->save('php://output');
			
			exit();
		}
		
        $data = Db::name('order')->where($where)->order('add_time desc')->paginate(10, false,['query'=>request()->param()]);
		
		$collecter = Db::name('user')->where('user_type', '3')->field('id, user_nickname')->select();

        $this->assign('data', $data);
        $this->assign('page', $data->render());
		$this->assign('collecter', $collecter);
        return $this->fetch();
    }
	
	/**
     * 导入订单
     * @adminMenu(
     *     'name'   => '订单列表',
     *     'parent' => 'portal/Orders/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '导入订单',
     *     'param'  => ''
     * )
     */
	public function import(){

		if ($this->request->isPost()) {
			$file = $this->request->param('file','', 'trim');	
			//读取文件
			$file_url = './upload/' . $file;
			$extension = strtolower( pathinfo($file_url, PATHINFO_EXTENSION) );
			if ($extension =='xlsx') {
				$objReader = new \PHPExcel_Reader_Excel2007();
				$objExcel = $objReader ->load($file_url);
			} else if ($extension =='xls') {
				$objReader = new \PHPExcel_Reader_Excel5();
				$objExcel = $objReader->load($file_url);
			}
			$excel_array=$objExcel->getsheet(0)->toArray();   //转换为数组格式
			array_shift($excel_array);  //删除第一个数组(标题);

			//获取品类列表
			$goods = Db::name('goods_cate')->where('children', 0)->field('id, name')->select()->toArray();
			foreach($goods as $v){
				$goods_list[$v['name']] = $v;
			}
			
			//获取回收员列表
			$userModel = new UserModel();
			$collecter = $userModel->get_collecter_list();
			foreach($collecter as $v){
				$collecter_list[$v['user_nickname']] = $v;
			}

			$log = '';
			$data = [];
			
			//处理数据
			foreach($excel_array as $k=>$v){
				$_goods_array 	= explode("\n", trim($v[2], "\n"));
				$_num_array 	= explode("\n", trim($v[3], "\n"));
				
				if(count($_goods_array ) != count($_num_array)){
					$log .= "第" .($k + 2). "行资源数据不匹配";
					break;
				}
				
				foreach($_goods_array as $key=>$g){
					$_goods_data[$key] = ['id'=>$goods_list[$g]['id'], 'name'=>$g, 'num'=>$_num_array[$key]];
				}
				
				if(empty($collecter_list[$v[5]])){
					$log .= "第" .($k + 2). "行回收员不匹配";
					break;
				}
				
				$_user = $userModel->get_user_info(['user_nickname'=> $v[6]]);
				if(empty($_user)){
					$log .= "第" .($k + 2). "行住户信息不匹配";
					break;
				}
				
				$data[] = [
							'order_sn'=>$v[1],
							'add_time'=>strtotime($v[0]),
							'points_number'=>$v[4],
							'collecter_name'=>$v[5],
							'collecter_id'=>$collecter_list[$v[5]]['id'],
							'goods'=>json_encode($_goods_data),
							'buyer_id' => $_user['id'],
							'buyer_name' => $_user['user_nickname'],
							'buyer_addr' => $v[7],
							'buyer_phone' => $v[8],
						];
				unset($_goods_array);
				unset($_num_array);
				unset($_goods_data);

			}
			
			if(empty($log) && $data){
				Db::name('order')->insertAll($data);
			}
			
			if(!empty($log)){	
				$this->error($log);
			}else{
				$this->success('导入成功!', url('Orders/index'));
			}
		}
		
		return $this->fetch();
    }
	
	
	/**
	 *	订单详情
	 */
	public function detail(){
		$id	= $this->request->param('id', 0, 'intval');
		if($id > 0){
			$info = Db::name('order')->where(['order_id'=>$id])->find();
			
			if($info){
				$info['user'] = Db::name('user')->where(['id'=>$info['buyer_id']])->find();
				
				$info['goods'] = json_decode($info['goods'], true);
				foreach($info['goods'] as $k=>$v){
					$info['goods'][$k]['p'] = isset($v['p']) ? $v['p'] : 0;
					$info['goods'][$k]['point'] = isset($v['point']) ? $v['point'] : 0;
				}
			}
			
			$this->assign($info);
			return $this->fetch();
		}else{
			$this->error('操作错误!');
		}
	}
	
	/**
	 *	回收机订单列表
	 */
	public function machine(){
		$param = $this->request->param();
		
		$where = [];
		$sn = empty($param['sn']) ? '' : $param['sn'];
        if (!empty($sn)) {
            $where['order_sn'] = ['like', "%$sn%"];
        }
		
		$startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime   = empty($param['end_time']) ? 0 : strtotime($param['end_time']);
        if (!empty($startTime) && !empty($endTime)) {
            $where['add_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['add_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['add_time'] = ['<= time', $endTime];
            }
        }
		
		$area_1 = isset($param['area_1']) ? trim($param['area_1']) : '';
		$area_2 = isset($param['area_2']) ? trim($param['area_2']) : '';
		$area_3 = isset($param['area_3']) ? trim($param['area_3']) : '';
		$area = '';
		if($area_1){
			$area .= $area_1 . ' ';
		}
		if($area_2){
			$area .= $area_2 . ' ';
		}
		if($area_3){
			$area .= $area_3 . ' ';
		}
		if(trim($area)){
			$condition['user_addr'] = ['like', $area . '%'];
		}
		$this->assign('area_1', $area_1);
		$this->assign('area_2', $area_2);
		$this->assign('area_3', $area_3);
		
		$machine_type_1 = isset($param['machine_type_1']) ? intval($param['machine_type_1']) : 0;
		$machine_type_2 = isset($param['machine_type_2']) ? intval($param['machine_type_2']) : 0;
		$machine_type = [];
		if($machine_type_1){
			$machine_type['machine_type_1']= $machine_type_1;
		}
		if($machine_type_2){
			$machine_type['machine_type_2']= $machine_type_2;
		}
		if($machine_type){
			$condition['more'] = json_encode($machine_type);
		}
		$this->assign('machine_type_1', $machine_type_1);
		$this->assign('machine_type_2', $machine_type_2);
		
		if(isset($condition)){
			$condition['user_type'] = 4;
			$machines = Db::name('user')->where($condition)->Field('id')->select()->toArray();
			$machine_in = [];
			foreach($machines as $v){
				$machine_in[] = $v['id'];
			}
			$where['mid'] = ['in', $machine_in];
		}
		
		$where['order_from'] = 4;
		
        $data = Db::name('order')->where($where)->order('add_time desc')->paginate(10, false,['query'=>request()->param()]);

        $this->assign('data', $data);
        $this->assign('page', $data->render());
		
		$area = Db::name('area')->where(['parent_id'=>0])->select();
		$machine_type = Db::name('goods_cate')->where(['parent_id'=>0, 'is_machine'=>1])->select();
		$this->assign('area', $area);
		$this->assign('machine_type', $machine_type);
		
		$machine = [];
		foreach(Db::name('user')->where(['user_type'=>4])->select() as $v){
			$machine[$v['id']] = $v['user_addr'];
		}
		$this->assign('machine', $machine);
		
        return $this->fetch();
    }
	
}