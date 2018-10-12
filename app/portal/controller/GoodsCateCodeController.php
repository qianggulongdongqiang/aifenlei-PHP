<?php
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;
use app\portal\model\GoodsCateCodeModel;

class GoodsCateCodeController extends AdminBaseController{

    public function index(){
		$param = $this->request->param();
		$condition = [];
		$cid = isset($param['id']) ? intval($param['id']) : 0 ;
		$condition['cid'] = $cid;


		$Model = new GoodsCateCodeModel();
		
		$data = $Model
				->where($condition)
				->with('cate')
				->order("id DESC")
				->paginate(10, false,['query'=>request()->param()]);
				
        $this->assign('data', $data);
		$this->assign('cid', $cid);
        $this->assign('page', $data->render());


        return $this->fetch();
    }

    public function add(){
		$param = $this->request->param();
		$cid = isset($param['cid']) ? intval($param['cid']) : 0 ;
		if(!$cid){
			$this->error('操作错误!');
		}
		$this->assign('cid', $cid);
        return $this->fetch();
    }

    public function addPost(){
        if ($this->request->isPost()) {
            $data   = $this->request->param();

            $result = $this->validate($data, 'GoodsCateCode');
            if ($result !== true) {
                $this->error($result);
            }
			
			//排重
			if(Db::name('GoodsCateCode')->where(['code'=>$data['code']])->count() > 0){
				$this->error('Code已经存在!');	
			}

			$result = Db::name('GoodsCateCode')->insert($data);
			
			if($result)	{
				$this->success('添加成功!', url('GoodsCateCode/index',['id'=>$data['cid']]));
			}else{
				$this->error('添加失败!');	
			}
        }

    }
	
	/**
     * 导入
     */
	public function import(){
		$param = $this->request->param();
		$cid = isset($param['cid']) ? intval($param['cid']) : 0 ;
		if(!$cid){
			$this->error('操作错误!');
		}
		$this->assign('cid', $cid);
	
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
				$re = Db::name('GoodsCateCode')->where(['code'=>$v[0]])->find();
				if($re){
					$log .= "第" .($k + 2). "行数据" .$v[0]. "已经存在";
					break;
				}
				
				$data[] = [
							'cid'=>$cid,
							'code'=>$v[0]
						];
			}
			
			if(empty($log) && $data){
				Db::name('GoodsCateCode')->insertAll($data);
			}
			
			if(!empty($log)){	
				$this->error($log);
			}else{
				$this->success('导入成功!', url('GoodsCateCode/index', ['id'=>$cid]));
			}
		}

		return $this->fetch();
    }
	
	public function delete(){
        $id = $this->request->param('id', 0, 'intval');
		
        if (Db::name('GoodsCateCode')->where(["id" => $id])->delete() !== false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
}
