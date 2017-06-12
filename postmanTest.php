<?php
 
class SetValue 
{
	// json文件位置
	private $path;
 
	public function __construct($path)
	{
		$this->path = $path;
	}
 
	public function run()
	{
		if (empty($this->path)) {
			echo "请选择文件!";
			die;
		}
		if (empty(is_file($this->path))) {
			echo "文件不存在!";
			die;
		}
		$data = json_decode(file_get_contents($this->path),true);
        foreach ($data['item'] as $k => $v) {
        	foreach ($v['item'] as $k2 => $v2) {	
        		$data['item'][$k]['item'][$k2]['event'] = $this->getTests();	// 接口测试反馈event
        		$data['item'][$k]['item'][$k2]['event'][1]['listen'] = 'prerequest';
        		$data['item'][$k]['item'][$k2]['event'][1]['script']['type'] = 'text/javascript';
 
        		$url = !empty($v2['request']['url']['raw']) ? $v2['request']['url']['raw'] : $v2['request']['url'];	// 判断url是否为数组
        		if (strpos($url,'/:')) {
        			// url中类似 /:id 存在的处理
        			$arr = explode(':',$url);
        			unset($data['item'][$k]['item'][$k2]['request']['url']);	//	有可能为数组
        			$data['item'][$k]['item'][$k2]['request']['url'] = $this->getUrl($arr[0],$arr[1]);
        			$data['item'][$k]['item'][$k2]['event'][1]['script']['exec'][] = $this->getUrlScript($arr[1]);	
        		} else {
        			$data['item'][$k]['item'][$k2]['request']['url'] = $this->getUrl($v2['request']['url']);
        			$urlencoded = $v2['request']['body']['urlencoded'];
        			$formdata   = $v2['request']['body']['formdata'];
        			$body_type  = $urlencoded ? $urlencoded : $formdata;	// 判断是form-data还是x-www-form-urlencoded
        			$column     = $urlencoded ? 'urlencoded' : 'formdata';
        			if (!empty($body_type)) {
	        			foreach ($body_type as $k3 => $v3) {
	        				$data['item'][$k]['item'][$k2]['request']['body'][$column][$k3]['value'] = "{{".$v3['key']."}}";	// 值
	        				$data['item'][$k]['item'][$k2]['event'][1]['script']['exec'][] = $this->getExecScript($v3['key'],$v3['value']);	 // 设置pre-srcipt
	        			}
	        		}
        		}
 
        	}
        }
        file_put_contents("test.json", json_encode($data));
        echo json_encode($data);
	}
 
	/**
	 * 获取接口测试反馈event
	 * @return [type] [description]
	 */
	public function getTests()
	{
		$event[] = [
			"listen" => "test",
			"script" => [
				"type" => "text/javascript",
				"exec" => [
                        "if (responseCode.code == 200) {",
                        "	var jsonData = JSON.parse(responseBody);",
                        "	if (jsonData.code == 200 || jsonData.status == 1) {",
                        "		if (jsonData.code == 200) {",
                        "			tests['success：' + jsonData.code] = jsonData.code == 200;",
                        "		} else {",
                        "			tests['success：' + jsonData.status] = jsonData.status == 1;",
                        "		}",
                        "	} else {",
                        "		if (jsonData.code == 400) {",
                        "			tests['error：' + jsonData.error] = jsonData.code == 400;",
                        "		} else {",
                        "			tests['error：' + jsonData.info] = jsonData.status === 0;",
                        "		}",
                        "	}",
                        "}",
                    ]
			],
		];
		return $event;
	}
 
	/**
	 * 根据key-value返回测试用例
	 * @param  [type] $key [字段名]
	 * @param  [type] $value [描述值]
	 * @return [type]      [description]
	 */
	public function getExecScript($key,$value)
	{	
		if (strpos($value, 'array(number)') !== false) {
			// array(number)
			$script = 'postman.setEnvironmentVariable("'.$key.'",(function(){var arr = [20,30,40]; var new_arr = [];for(i=0;i<4;i++){index = Math.floor((Math.random()*arr.length));new_arr[i] = arr[index];}return new_arr})()) //随机在20,30,40里选择4位组成数组，可修改范围和数组的个数';
		} elseif(strpos($value, 'array(int') !== false) {
			// array(integer) 或者 array(int)
			$script = 'postman.setEnvironmentVariable("'.$key.'",(function(){var max = 20;var min = 10; var n = 4; var arr =[];for(i=0;i<n;i++) {arr[i] = Math.floor(Math.random()*(max-min) +min);} return arr; })()) // 随机生成10-20的4个数字组成数组，可修改范围和个数';	
		} elseif(strpos($value, 'array') !== false || strpos($value, 'mixed') !== false) {
			// array(string) 或者 mixed
			$script = 'postman.setEnvironmentVariable("'.$key.'",(function(){var char_len = 8;var arr_len = 4;var chars = "qwertyuiopasdfghjklzxcvbnm";var maxPos = chars.length;var str = "";var arr = [];for (i=0;i<arr_len;i++) {for (j=0;j<char_len;j++) {str += chars.charAt(Math.floor(Math.random() * maxPos));}arr[i] = str;str = "";}return arr})() ) // 随机生成4个每项为8个小写字母组成的字符串的数组，可修改数组个数和字符串个数';	
		} elseif (strpos($value,'number') !== false) {
			// number，默认在0和1中选择
			$script = 'postman.setEnvironmentVariable("'.$key.'", (function(){var arr = [0,1];var index = Math.floor((Math.random()*arr.length));return arr[index]})())  // 随机返回0或者1,可以修改[0,1]中的数字';
		} elseif (strpos($value,'int') !== false) {
			// 整数
			$script = 'postman.setEnvironmentVariable("'.$key.'", (function(){var max = 20;var min = 10;var value = Math.floor(Math.random()*(max-min) +min); return value })())   // 随机[10-20]的整数,可修改区间';
		} elseif (strpos($value,'text') !== false) {
			// text
			$script = 'postman.setEnvironmentVariable("'.$key.'",(function(){ var n = Math.ceil(Math.random()*100); var str="\'";for(var i=0; i<n; i++){ str += "\\\u"+(Math.ceil(19968 + Math.random()*20901)).toString(16) }  str += "\'"; return eval(str)})() )      // 随机生成1-100个中文';
		} elseif(strpos($value,'string') !== false) {
			// 字符串（只有小写字母）
			$script = 'postman.setEnvironmentVariable("'.$key.'",(function(){var len = 8;var chars = "qwertyuiopasdfghjklzxcvbnm";var maxPos = chars.length;var str = "";for (i=0;i<len;i++) {str += chars.charAt(Math.floor(Math.random() * maxPos));}return str})() )	//随机返回8个小写字母，可修改个数和增加大写字母或者数字等' ;	
		} elseif(strpos($value,'float') !== false) {
			// 浮点数 
			$script = 'postman.setEnvironmentVariable("'.$key.'",((Math.random()*10).toFixed(2)))';
		} else {
			$script = '';
		}
 
		return $script; 
	}
 
	/**
	 * 根据url返回环境变量(/:id类型)
	 * @param  [type] $key [字段]
	 * @return [type]      [description]
	 */
	public function getUrlScript($key)
	{
		$script = 'postman.setEnvironmentVariable("id", (function(){var max = 20;var min = 10;var value = Math.floor(Math.random()*(max-min) +min); return value })())   // 随机[10-20]的整数,可修改区间';
		return $script;
	}
 
	/**
	 * 判断url中是否含有{{url}}
	 * @param string $v1 [url,为/:id时为url的前半部分]
	 * @param string $v2 [为/:id时才会传,为id]
	 */
	public function getUrl($v1, $v2 = '')
	{
		if (strpos($v1, "{{url}}") === false) {
			if (empty($v2)) {
				$ret = "{{url}}/".$v1;
			} else {
				$ret = "{{url}}/".$v1."{{".$v2."}}";
			}
		} else {
			if (empty($v2)) {
				$ret = $v1;
			} else {
				$ret = $v1."{{".$v2."}}";
			}
		}
		return $ret;
	}
 
}
 
error_reporting(E_ALL ^ E_NOTICE);
$setValue = new SetValue($_GET['path']);
$setValue->run();
 
function p($obj)
{
	echo "<pre>";
	print_r($obj);
	echo "</pre>";
	die;
}