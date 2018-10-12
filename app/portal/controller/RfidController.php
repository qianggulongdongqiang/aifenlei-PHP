<?php
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\RfidModel;

class RfidController extends AdminBaseController{

    public function index(){
		$param = $this->request->param();
		$condition = [];
		$type = isset($param['type']) ? intval($param['type']) : 0 ;
		if($type == 1){
			$condition['user_id'] = ['neq', 0];
		}elseif($type == 2){
			$condition['user_id'] = 0;
		}

		$rfidModel = new RfidModel();
		
		$data = $rfidModel
				->where($condition)
				->with('user')
				->order("add_time DESC")
				->paginate(10, false,['query'=>request()->param()]);
				
        $this->assign('data', $data);
		$this->assign('type', $type);
        $this->assign('page', $data->render());


        return $this->fetch();
    }

    public function add(){
        return $this->fetch();
    }

    public function addPost(){
        if ($this->request->isPost()) {
            $data   = $this->request->param();

            $result = $this->validate($data, 'Rfid');
            if ($result !== true) {
                $this->error($result);
            }
			
			//排重
			if(Db::name('Rfid')->where(['code'=>$data['code']])->count() > 0){
				$this->error('Code已经存在!');	
			}

			$data['add_time'] = time();
			$result = Db::name('Rfid')->insert($data);
			
			if($result)	{
				$this->success('添加成功!', url('Rfid/index'));
			}else{
				$this->error('添加失败!');	
			}
        }

    }

    public function edit(){
        $id = $this->request->param('id', 0, 'intval');
        if ($id > 0) {
            $data = Db::name('Rfid')->where(['id'=>$id, 'user_id'=> 0])->find();

            $this->assign($data);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }
    }

    public function editPost()
    {

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
			$d['id'] = $data['id'];
			$d['user_id'] = $user['id'];
			$d['bind_time'] = time();
            $result = Db::name('Rfid')->update($d);

            $this->success('绑定成功!', url('Rfid/index'));

        }
    }

    public function unbind(){
        $param           = $this->request->param();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $result       = Db::name('Rfid')->where(['id' => $id])->update(['user_id'=>0, 'bind_time'=>0]);

            $this->success("解绑成功！", '');

        }

        if (isset($param['ids'])) {
            $ids     = $this->request->param('ids/a');
			$result  = Db::name('Rfid')->where(['id' => ['in', $ids]])->update(['user_id'=>0, 'bind_time'=>0]);
            
            $this->success("解绑成功！", '');
        }
    }
	
	/**
     * 导入RFID
     * @adminMenu(
     *     'name'   => 'RFID列表',
     *     'parent' => 'portal/RFID/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '导入RFID',
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


			$log = '';
			$data = [];
			
			//处理数据
			foreach($excel_array as $k=>$v){
				$re = Db::name('Rfid')->where(['code'=>$v[0]])->find();
				if($re){
					$log .= "第" .($k + 2). "行数据" .$v[0]. "已经存在";
					break;
				}
				
				$data[] = [
							'add_time'=>time(),
							'code'=>$v[0]
						];
			}
			
			if(empty($log) && $data){
				Db::name('Rfid')->insertAll($data);
			}
			
			if(!empty($log)){	
				$this->error($log);
			}else{
				$this->success('导入成功!', url('Rfid/index'));
			}
		}
		
		return $this->fetch();
    }
	
	public function delete(){
        $id = $this->request->param('id', 0, 'intval');
		
        if (Db::name('Rfid')->where(["id" => $id])->delete() !== false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
}
