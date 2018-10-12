<?php
namespace app\user\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use think\Validate;
use app\api\model\UserMsgModel;

/**
 * Class AdminIndexController
 * @package app\user\controller
 *
 * @adminMenuRoot(
 *     'name'   =>'用户管理',
 *     'action' =>'default',
 *     'parent' =>'',
 *     'display'=> true,
 *     'order'  => 10,
 *     'icon'   =>'group',
 *     'remark' =>'用户管理'
 * )
 *
 * @adminMenuRoot(
 *     'name'   =>'用户组',
 *     'action' =>'default1',
 *     'parent' =>'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   =>'',
 *     'remark' =>'用户组'
 * )
 */
class AdminIndexController extends AdminBaseController{
	
	public $type = 2;
	
	public function _initialize(){
		parent::_initialize();
		$type = $this->request->param('type', 2, 'intval');
		if($type > 1)	$this->type = $type;
		$this->assign('type', $this->type);
	}
    /**
     * 后台列表
     * @adminMenu(
     *     'name'   => '住户',
     *     'parent' => 'default1',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '住户',
     *     'param'  => ''
     * )
     */
    public function index(){
		
        $where   = ["user_type" => $this->type];
        $request = input('request.');
		
		if($this->type == 2){
			$where['mobile'] = ['neq' , ''];
		}

        if (!empty($request['uid'])) {
            $where['id'] = intval($request['uid']);
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['user_login|user_nickname|mobile']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user');
		
		$order = isset($request['order']) ? intval($request['order']) : 1;
		switch($order){
			case 1:$order_by = 'create_time DESC';break;
			case 2:$order_by = 'score DESC';break;
			case 3:$order_by = 'score asc';break;
			default:$order_by = 'score DESC';break;
		}
		$this->assign('order', $order);
		
		$state = isset($request['state']) ? intval($request['state']) : -1;
		if($state > 0){
			$where['user_status'] = $state;
		}elseif($state == 0){
			$where['user_status'] = ['in', '0,1,2'];
		}
		$this->assign('state', $state);
		
		$area_1 = isset($request['area_1']) ? trim($request['area_1']) : '';
		$area_2 = isset($request['area_2']) ? trim($request['area_2']) : '';
		$area_3 = isset($request['area_3']) ? trim($request['area_3']) : '';
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
			$where['user_addr'] = ['like', $area . '%'];
		}
		$this->assign('area_1', $area_1);
		$this->assign('area_2', $area_2);
		$this->assign('area_3', $area_3);
		
		$machine_type_1 = isset($request['machine_type_1']) ? intval($request['machine_type_1']) : 0;
		$machine_type_2 = isset($request['machine_type_2']) ? intval($request['machine_type_2']) : 0;
		$machine_type = [];
		if($machine_type_1){
			$machine_type['machine_type_1']= $machine_type_1;
		}
		if($machine_type_2){
			$machine_type['machine_type_2']= $machine_type_2;
		}
		if($machine_type){
			$where['more'] = json_encode($machine_type);
		}
		$this->assign('machine_type_1', $machine_type_1);
		$this->assign('machine_type_2', $machine_type_2);
		

        $list = $usersQuery->whereOr($keywordComplex)->where($where)->order($order_by)->paginate(10);
		$list->appends($request);

        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
		
		$title = ['2'=>'住户', '3'=>'回收员', '4'=>'回收机'];
		$this->assign('title', $title[$this->type]);
		
		$area = Db::name('area')->where(['parent_id'=>0])->select();
		$machine_type = Db::name('goods_cate')->where(['parent_id'=>0, 'is_machine'=>1])->select();
		$this->assign('area', $area);
		$this->assign('machine_type', $machine_type);
		
        // 渲染模板输出
        return $this->fetch('index');
    }
	
	public function collecter(){
		$this->type = 3;
		$this->assign('type', $this->type);
		return $this->index();	
	}
	
	public function machine(){
		$this->type = 4;
		$this->assign('type', $this->type);
		return $this->index();	
	}

    /**
     * 拉黑
     * @adminMenu(
     *     'name'   => '拉黑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '拉黑',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("user")->where(["id" => $id, "user_type" => $this->type])->setField('user_status', 0);
            if ($result) {
                $this->success("拉黑成功！", "adminIndex/index");
            } else {
                $this->error('拉黑失败,用户不存在！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 启用
     * @adminMenu(
     *     'name'   => '启用',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '启用',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("user")->where(["id" => $id, "user_type" => $this->type])->setField('user_status', 1);
            $this->success("会员启用成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }
	
	/**
     * 新增
     * @adminMenu(
     *     'name'   => '新增',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '新增',
     *     'param'  => ''
     * )
     */
	public function add(){
        if ($this->request->isPost()) {
			$data             = $this->request->post();
			unset($data['type']);
			$data['user_type'] = $this->type;
			$data['birthday'] = isset($data['birthday']) ? strtotime($data['birthday']) : 0;	
			
			$validate = new Validate([
                'user_login' => 'require',
                'user_pass' => 'require|min:6|max:32',
            ]);
            $validate->message([
                'user_login.require' => '账户编号不能为空',
                'user_pass.require' => '密码不能为空',
                'user_pass.max'     => '密码不能超过32个字符',
                'user_pass.min'     => '密码不能小于6个字符',
            ]);
			
			if (!$validate->check($data)) {
                $this->error($validate->getError());
            }
			
			if($data['user_type'] == '2'){
				$validate = new Validate([
					'mobile' => 'require|mobile'
				]);
				$validate->message([
					'mobile.require' => '手机号必须填写',
					'mobile.mobile'     => '手机号码格式不正确',
				]);	
				if (!$validate->check($data)) {
					$this->error($validate->getError());
				}
				
				if(Db::name('user')->where(['mobile'=>$data['mobile']])->count() > 0){
					$this->error("手机号已经存在！");
				}
			}
			
			$data['user_pass'] = cmf_password($data['user_pass']);
			$data['user_addr'] = trim($data['area_1'] . ' ' . $data['area_2'] . ' ' . $data['area_3'] . ' ' . $data['user_addr']);
			$data['area_id'] = isset($data['area_id']) ? intval($data['area_id']) : 0;
			unset($data['area_1']);
			unset($data['area_2']);
			unset($data['area_3']);
			$more['machine_type_1'] = isset($data['machine_type_1']) ? intval($data['machine_type_1']) : 0;
			$more['machine_type_2'] = isset($data['machine_type_2']) ? intval($data['machine_type_2']) : 0;
			$data['more'] = json_encode($more);
			unset($data['machine_type_1']);
			unset($data['machine_type_2']);

			$data['create_time'] = time();
			$result             = DB::name('user')->insertGetId($data);
			if ($result !== false) {
				$this->success("添加成功！", url("admin_index/index", ['type'=>$this->type]));
			} else {
				$this->error("添加失败！");
			}
		}else{
			$area = Db::name('area')->where(['parent_id'=>0])->select();
			$machine_type = Db::name('goods_cate')->where(['parent_id'=>0, 'is_machine'=>1])->select();
			$this->assign('area', $area);
			$this->assign('machine_type', $machine_type);
			return $this->fetch();
		}
    }
	
	/**
     * 编辑
     * @adminMenu(
     *     'name'   => '编辑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑',
     *     'param'  => ''
     * )
     */
	public function edit(){
		if ($this->request->isPost()) {
			$data             = $this->request->post();
			$data['birthday'] = isset($data['birthday']) ? strtotime($data['birthday']) : 0;
			$data['user_addr'] = trim($data['area_1'] . ' ' . $data['area_2'] . ' ' . $data['area_3'] . ' ' . $data['user_addr']);
			$data['area_id'] = isset($data['area_id']) ? intval($data['area_id']) : 0;

			unset($data['area_1']);
			unset($data['area_2']);
			unset($data['area_3']);
			
			$more['machine_type_1'] = isset($data['machine_type_1']) ? intval($data['machine_type_1']) : 0;
			$more['machine_type_2'] = isset($data['machine_type_2']) ? intval($data['machine_type_2']) : 0;
			$data['more'] = json_encode($more);
			unset($data['machine_type_1']);
			unset($data['machine_type_2']);
			
			$user_pass = '';
			
			if($data['user_pass']){
				$validate = new Validate([
					'user_pass' => 'require|min:6|max:32',
				]);
				$validate->message([
					'user_pass.max'     => '密码不能超过32个字符',
					'user_pass.min'     => '密码不能小于6个字符',
				]);
				if (!$validate->check($data)) {
					$this->error($validate->getError());
				}
				$user_pass = $data['user_pass'];
				$data['user_pass'] = cmf_password($data['user_pass']);
			}else{
				unset($data['user_pass']);
			}
			
			
            $create_result    = Db::name('user')->update($data);
            if ($create_result !== false) {
				
				$user = Db::name('user')->where(["id" => $data['id']])->field('more,code,signature,user_type')->find();
				if($user['user_type'] == 4 && $user['code']){	//推送消息给回收机
					if($user_pass){
						$user['user_pass'] = $user_pass;
					}
					$user['more'] = json_decode($user['more'], true);
					if($user['more']['machine_type_1']){
						$user['machine_type_1'] = Db::name('goods_cate')
												->where(['id'=>$user['more']['machine_type_1']])
												->field('id,name,unit_name,unit,is_qrcode,purchasing_point')
												->find();
					}else{
						$user['machine_type_1'] = [];
					}
					if($user['more']['machine_type_2']){
						$user['machine_type_2'] = Db::name('goods_cate')
												->where(['id'=>$user['more']['machine_type_2']])
												->field('id,name,unit_name,unit,is_qrcode,purchasing_point')
												->find();
					}else{
						$user['machine_type_2'] = [];
					}
					unset($user['more']);
					
					
					$userMsgModel = new UserMsgModel();
					$info = [
							'content'=>'修改配置',
							'user'=>$user,
							'type'=>'20'
						];
					
					$res = $userMsgModel->push($user['code'], '', $info, 'm');
				}
				
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
		}else{
			$id   = $this->request->param('id', 0, 'intval');
			$user = Db::name('user')->where(["id" => $id])->find();
			
			$_addr = explode(' ', $user['user_addr']);
			
			$user['user_addr'] = end($_addr);
			$user['area_1'] = '';
			$user['area_2'] = '';
			$user['area_3'] = '';
			
			if(isset($_addr[0])){
				$user['area_1'] = $_addr[0];
			}
			
			if(isset($_addr[1])){
				$user['area_2'] = $_addr[1];
			}
			
			if(isset($_addr[2])){
				$user['area_3'] = $_addr[2];
			}
			
			$user['more'] = json_decode($user['more'], true);
			
			$this->assign($user);
			
			$area = Db::name('area')->where(['parent_id'=>0])->select();
			$this->assign('area', $area);
			
			$machine_type = Db::name('goods_cate')->where(['parent_id'=>0, 'is_machine'=>1])->select();
			$this->assign('machine_type', $machine_type);
			
			return $this->fetch();
		}
    }
	
	public function getArea(){
		if ($this->request->isPost()) {
			$data             = $this->request->post();
			$area = Db::name('area')->where(['parent_id'=>$data['id']])->select();
			$this->success('', '', $area);
		}
	}
	
	public function getMachineType(){
		if ($this->request->isPost()) {
			$data             = $this->request->post();
			$area = Db::name('goods_cate')->where(['parent_id'=>$data['id']])->select();
			$this->success('', '', $area);
		}
	}
	
	public function delete(){
        $id = $this->request->param('id', 0, 'intval');
		
        if (Db::name('user')->where(["id" => $id, 'user_type'=>2])->update(['mobile'=>'', 'openid'=>'']) !== false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
	
	public function delete_collecter(){
        $id = $this->request->param('id', 0, 'intval');
		
        if (Db::name('user')->where(["id" => $id, 'user_type'=>3])->delete() !== false) {
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }
	
	public function score(){
		if ($this->request->isPost()) {
			$data             = $this->request->post();
			$data['score'] = isset($data['score']) ? intval($data['score']) : 0;
			
			if($data['score'] == 0){
				$this->error("积分不能为0！");
			}
			
			if($data['score'] < 0){
				$user = Db::name('user')->where(["id" => $data['id']])->find();
				if($user['score'] + $data['score'] < 0){
					$this->error("积分不足！");
				}
			}
			
			
            $create_result    = Db::name('user')->where(['id'=>$data['id']])->setInc('score', $data['score']);
            if ($create_result !== false) {
				
				Db::name('user_score_log')->insert([
													'user_id'=>$data['id'],
													'create_time'=>time(),
													'action'=>'change:' . trim($data['remark']),
													'score'=> $data['score']
												]);
				
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
		}else{
			$id   = $this->request->param('id', 0, 'intval');
			$user = Db::name('user')->where(["id" => $id])->find();
			$this->assign($user);
			
			return $this->fetch();
		}
    }
	
	
	/**
	 *	状态日志
	 */
	public function machine_state_log(){	
		$where['mid'] = $this->request->param('id', 0, 'intval');;

        $list = db::name('machine_state_log')->where($where)->order('time desc')->paginate(10);

        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
		
        // 渲染模板输出
        return $this->fetch('machine_state_log');
    }
}
