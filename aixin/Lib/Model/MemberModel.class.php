<?php
/*
 * MUST_VALIDATE	必须验证 不管表单是否有设置该字段
 * VALUE_VALIDATE	值不为空的时候才验证
 * EXISTS_VALIDATE	表单存在该字段就验证   (默认)
 */
// 用户模型
class MemberModel extends Model {
	protected $_validate	=	array(
		array('account','/^\w{4,16}$/i','会员编号格式错误，字母或数字 4-16位'),//  \w等价于[A-Za-z0-9_]
		array('account','require','会员编号必须'),
		array('account','','会员编号已经存在',self::EXISTS_VALIDATE,'unique'),
		
		array('password','require','登录密码必须'),
		array('repassword','require','确认登录密码必须'),
		array('repassword','password','登录确认密码不一致',self::EXISTS_VALIDATE,'confirm'),
		
		array('pwdtwo','require','二级密码必须'),
		array('repwdtwo','require','二级取款密码必须'),
		array('repwdtwo','pwdtwo','二级确认密码不一致',self::EXISTS_VALIDATE,'confirm'),
		
		array('parent_id','require','推荐人必须',self::EXISTS_VALIDATE ,'regex',self::MODEL_BOTH),
		array('parent_area','require','节点位置必须',self::EXISTS_VALIDATE,'regex',self::MODEL_BOTH),
		array('parent_area',array('A','B'),'节点位置非法',self::EXISTS_VALIDATE,'in',self::MODEL_BOTH),
		
		array('level',array(0,1,2,3,4,5),'级别非法',self::EXISTS_VALIDATE,'in',self::MODEL_UPDATE), //更新时 存在字段 验证
		
		array('realname','require','真实姓名不能为空'),
		
		array('tel','require','联系电话必须'),
		//array('tel','/((\d{11})|^((\d{7,8})|(\d{4}|\d{3})-(\d{7,8})|(\d{4}|\d{3})-(\d{7,8})-(\d{4}|\d{3}|\d{2}|\d{1})|(\d{7,8})-(\d{4}|\d{3}|\d{2}|\d{1}))$)/','联系电话格式不正确'),
		array('idcard','require','身份证号必须'),
		//array('idcard','/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{4}$/','身份证号不正确'),
		
		array('status',array(-1,0,1),'用户状态非法',self::VALUE_VALIDATE,'in'),//-1-删除 0-禁用 1-正常
	);

	protected $_auto		=	array(
		array('password','pwdHash',self::MODEL_INSERT,'function'),
		array('pwd_money','pwdHash',self::MODEL_INSERT,'function'),
		array('level','0',self::MODEL_INSERT), //默认级别-0 临时会员
		array('create_time','time',self::MODEL_INSERT,'function'),
	);
	
	/**
	 * 管理员新增用户
	 * @param $data create的数据
	 */
	public function addByMgr($data=array()) {
		/*
		 * 管理员从后台     新增会员
		 * 新增会员的status=1, level=0, 并在levelup表中插入待审核的升级记录
		 */
		if (empty($data)) $createflag = $this->create();
		else $createflag = $this->create($data);
		if (false === $createflag) return false;
	}
		

	/**
	 * 返回$id用户有几个直推下级 0,1,2
	 * @param int $id 要判断的用户ID
	 */
	public function sonNums($id) {
		$return = 0; $condition = array();
		$condition['parent_id'] = $id;
		
		$condition['parent_area'] = 'A';
		$son_A = $this->findAble($condition);
		if ($son_A !== false && !empty($son_A)) $return++;
		
		$condition['parent_area'] = 'B';
		$son_B = $this->findAble($condition);
		if ($son_B !== false && !empty($son_B)) $return++;
		
		return $return;
	}
	
	/**
	 * 返回$id用户推荐体系的人数
	 * @param int $id 要判断的用户ID
	 */
	public function areaNums($id,$nums=0) {
		$return = $nums; $condition = array();
		$condition['parent_id'] = $id;
		
		$condition['parent_area'] = 'A';
		$son_A = $this->findAble($condition);
		if ($son_A !== false && !empty($son_A)) {
			$return++;
			$return = $this->areaNums($son_A['id'],$return);
		}
		
		$condition['parent_area'] = 'B';
		$son_B = $this->findAble($condition);
		if ($son_B !== false && !empty($son_B)) {
			$return++;
			$return = $this->areaNums($son_B['id'],$return);
		}
		
		return $return;
	}
		

	/**
	 * 获取用户升级应付金额
	 * @param  $mid
	 * @param  $basepoints
	 */
	public function getShould($mid , $basepoints) {
		$info = $this->find($mid);
		return get_shouldpay($info['level'], $basepoints);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	public function getMemberInfo($id=0) {
		$id = (int)$id;
		if ($id <= 0) $id = $_SESSION[C('USER_AUTH_KEY')];
		return $this->getById($id);
	}
	public function getMemberId($account){
		if (!empty($account)){
			return $this->where("account='".$account."'")->getField('id');
		}
	}
	
	/**
	 * 用户status in 1,3,4,5
	 */
	public function findAble($options=array()) {
		$where = array();
		if (is_numeric($options) || is_string($options)) {
			$where[$this->getPk()]  =   $options;
		}else {
			$where = $options;
		}
		if (empty($where['status'])) $where['status'] = array('in','1,3,4,5');
		return $this->where($where)->find();
	}
	
	/**
	 * 新增数据前, 验证 parent_area 和 parent_area_type
	 */
	protected function _before_insert($data, $options) {
		if (false === $this->chkPA($data)) {
			$this->error = '推荐人不存在';
			return false;
		}elseif (false === $this->chkPAT($data)) {
			$this->error = '节点位置已被占用';
			return false;
		}else {
			return true;
		}
	}
	
	/**
	 * 检测推荐人是否存在
	 */
	private function chkPA($data) {
		$condition = array();
		$condition['status'] = array('in','1,3,4,5');
		$condition['id'] = $data['parent_area'];
		$num = $this->where($condition)->count();
		if ($num > 0) return true;
		else return false;
	}
	/**
	 * 节点位置检测合法性
	 */
	private function chkPAT($data) {
		if ($data['parent_area_type'] != 'A' && $data['parent_area_type'] != 'B') {
			return false;
		}
		$condition = array();
		$condition['status'] = array('in','1,3,4,5');
		$condition['parent_area'] = $data['parent_area'];
		$condition['parent_area_type'] = $data['parent_area_type'];
		$num = $this->where($condition)->count();
		if ($num > 0) return false;
		else return true;
	}
	
	/**
	 * 检查会员是否满足 结算将条件
	 */
	public function jiesuanAble($id) {
		$condition = array();
		$condition['parent_area'] = $id;
		
		$condition['parent_area_type'] = 'A';
		$son_A = $this->findAble($condition);
		if ($son_A === false || empty($son_A)) return false;
		
		$condition['parent_area_type'] = 'B';
		$son_B = $this->findAble($condition);
		if ($son_B === false || empty($son_B)) return false;
		
		if ($this->sonNums($son_A['id']) != 2) return false;
		if ($this->sonNums($son_B['id']) != 2) return false;
		return true;
	}
	
}