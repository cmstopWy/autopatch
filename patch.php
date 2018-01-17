<?php
//简单验证,参数个数及允许的操作
$allowAction = ['create','put','get','install','uninstall'];
$params      = $argv;
if(count($params) != 3 && count($params) != 4){
	echo "参数个数不正确\n";exit;
}
if(!in_array($params[1],$allowAction)){
	echo "对应操作不允许\n";exit;
}
//定义路径
$cmstopCode = '/patch/mediacloud';//从git库克隆出的媒体云代码目录
$patchTmp   = '/patch';//补丁包临时存放目录
$uploadUrl  = 'http://patch.cmstop.com/upload.php';//补丁上传服务地址
$downloadUrl= 'http://patch.cmstop.com/mediacloud/';//补丁下载url前缀
$signkey    = 'Cmstop2016';//请求上传接口时的简单验证
$patchDir   = '/data/www/cloud-patch/patch';//补丁目录
$bakDir     = '/data/www/cloud-patch/source_bak';//备份目录
$codeDir    = '/data/www/Cloud';//项目媒体云代码目录
if($params[1] != 'install' || $params[2] != '-f'){
    //获取分支tag
    $tagInArr   = explode('-',$params[2]);
    $tag        = $tagInArr[0];
    //获取tag对应大版本
    $tagArr     = explode('.',$tag);
    $tagBig     = implode('.',[$tagArr[0],$tagArr[1],$tagArr[2]]);
}
if($params[1] == 'create'){
 	  if(!isset($params[2]) || !isset($params[3])){
        echo "create 操作需要 输入代码分支 和 对比的目标分支两个参数\n";exit;
    }
    chdir($cmstopCode);
    shell_exec("sudo git fetch origin");
    shell_exec("sudo git checkout -b ".$params[2]." origin/".$params[2]);
    $return = shell_exec("sudo git diff "."origin/".$params[3]." --name-only");
    $fileArr = explode("\n",$return);
    $dirname = $params[2];
    $tarname = $dirname.".tar.gz";
    if(file_exists($patchTmp.'/'.$dirname)){
            shell_exec("sudo rm -rf ".$patchTmp."/".$dirname);
    }
    if(file_exists($patchTmp.'/'.$tarname)){
            shell_exec("sudo rm -rf ".$patchTmp."/".$tarname);
    }
    shell_exec("sudo mkdir ".$patchTmp."/".$dirname);
    $filelog = [];
    foreach($fileArr as $k=>$v){
        if($v && file_exists($v)){
            $msg = shell_exec("sudo git log -1 ".$v);
            $msgArr = explode("\n",$msg);
            $authorArr = explode(":",$msgArr[1]);
            $msgAuthor = trim($authorArr[1]);
            shell_exec("sudo cp --parents ".$v." ".$patchTmp."/".$dirname."/");
            $filelog[] = $v." Last Modified By: ".$msgAuthor." From Patch: ".$params[2];
        }
    }
    shell_exec("sudo git checkout master");
    shell_exec("sudo git branch -D ".$params[2]);
    chdir($patchTmp.'/'.$dirname);
    shell_exec('sudo touch file.log');
    shell_exec('sudo chmod 777 file.log');
    if($filelog){
        foreach ($filelog as $k => $v) {
            shell_exec("sudo echo '".$v."' >> ".$patchTmp."/".$dirname."/file.log");
        }
    }
    chdir($patchTmp);
    shell_exec("sudo tar -zcvf ".$tarname." ".$dirname);
    if(file_exists($patchTmp.'/'.$dirname)){
            shell_exec("sudo rm -rf ".$patchTmp."/".$dirname);
    }
    if(file_exists($patchTmp.'/'.$tarname)){
        echo "补丁包创建成功\n";exit;
    }else{
        echo "补丁包创建失败\n";exit;
    }
}else if($params[1] == 'put'){
    $dirname = $params[2];
    $tarname = $dirname.".tar.gz";
    if(!file_exists($patchTmp.'/'.$tarname)){
        echo "对应补丁包不存在,请先执行创建补丁包操作\n";exit;
    }
    $request = 'POST';
    $post = [
        'file'    => '@/'.$patchTmp.'/'.$tarname,
        'signkey' => $signkey
    ];
    $timeout = 600;
    $res = _sendRequest($uploadUrl, $request, $post, $timeout);
    $resData = json_decode($res,true);
    if($resData['status']){
        if(file_exists($patchTmp.'/'.$tarname)){
            shell_exec("sudo rm -rf ".$patchTmp."/".$tarname);
        }
        echo "补丁包上传成功,下载地址为: \n".$resData['data']['file_url']."\n";
        exit;
    }else{
        echo "补丁包上传失败\n";
        exit;
    }
}else if($params[1] == 'get'){
    if(!file_exists($patchDir)){
        shell_exec('sudo mkdir -p '.$patchDir);
        shell_exec('sudo chmod 777 '.$patchDir);
    }
    $file_url = $downloadUrl.'/'.$tagBig.'/'.$params[2].'.tar.gz';
    if(file_exists($patchDir.'/'.$tagBig.'/'.$params[2])){
        shell_exec('sudo rm -rf '.$patchDir.'/'.$tagBig.'/'.$params[2]);
    }
    if(file_exists($patchDir.'/'.$tagBig.'/'.$params[2].'.tar.gz')){
        echo "补丁包已存在,无需下载\n";exit;
    }
    shell_exec("sudo wget -P ".$patchDir."/".$tagBig." '".$file_url."'");
}else if($params[1] == 'install' && $params[2] != '-f'){
    if(!file_exists($patchDir)){
        echo "补丁包目录不存在,请先执行补丁下载操作\n";exit;
    }
    $file_path = $patchDir.'/'.$tagBig.'/'.$params[2].'.tar.gz';
    if(!file_exists($file_path)){
        echo "补丁包不存在,请先执行补丁下载操作\n";exit;
    }
    chdir($patchDir.'/'.$tagBig);
    shell_exec('sudo tar -zxvf '.$params[2].'.tar.gz');
    $filenames = get_filenamesbydir($patchDir.'/'.$tagBig.'/'.$params[2]);
    $bakfiles  = [];
    foreach ($filenames as $v) {
        $bakfile    = str_replace($patchDir.'/'.$tagBig.'/'.$params[2].'/','',$v);
        $bakfiles[] = $bakfile;
    }
    //判定冲突文件
    $patchfile    = [];
    $conflictfile = [];
    if(!file_exists($patchDir.'/'.$tagBig.'/patchfile.log')){
        shell_exec('sudo touch patchfile.log');
        shell_exec('sudo chmod 777 patchfile.log');
    }else{
        $patchfile = readFileByLine('patchfile.log');
    }
    if($patchfile){
        $conflictfile = array_intersect($bakfiles,array_keys($patchfile));
        if($conflictfile){
            echo "补丁包文件冲突,冲突文件如下: \n\n";
            foreach ($conflictfile as $v) {
                if(array_key_exists($v,$patchfile)){
                    echo $patchfile[$v][count($patchfile[$v]) - 1]."\n";
                }
            }
            echo "\n基于冲突文件所在分支创建新补丁分支,下载新分支补丁包后,使用install -f安装对应分支\n";
            exit;
        }
    }
    //修改patchfile.log
    $filelog = readFileByLine($patchDir.'/'.$tagBig.'/'.$params[2].'/file.log');
    if($filelog){
        foreach ($filelog as $v) {
            foreach ($v as $value) {
                shell_exec("sudo echo '".$value."' >> ".$patchDir."/".$tagBig."/patchfile.log");
            }
        }
    }
    $dirname  = date('YmdHi',time()).'-'.$params[2];
    $tarname  = $dirname.'.tar.gz';
    if(file_exists($bakDir.'/'.$tagBig.'/'.$dirname)){
        shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname);
    }
    shell_exec('sudo mkdir -p '.$bakDir.'/'.$tagBig.'/'.$dirname);
    shell_exec('sudo chmod 777 '.$bakDir.'/'.$tagBig.'/'.$dirname);
    chdir($codeDir);
    foreach ($bakfiles as $v) {
        if(file_exists($v)){
            shell_exec('sudo cp --parents '.$v.' '.$bakDir.'/'.$tagBig.'/'.$dirname.'/');
        }
    }
    shell_exec('sudo mv '.$patchDir.'/'.$tagBig.'/'.$params[2].'/file.log '.$bakDir.'/'.$tagBig.'/'.$dirname.'/file.log');
    chdir($bakDir.'/'.$tagBig);
    shell_exec("sudo tar -zcvf ".$tarname." ".$dirname);
    if(file_exists($bakDir.'/'.$tagBig.'/'.$dirname)){
        shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname);
    }
    $str = "sudo cp -R ".$patchDir."/".$tagBig."/".$params[2]."/* ".$codeDir.'/';
    shell_exec($str);
    if(file_exists($patchDir.'/'.$tagBig.'/'.$params[2])){
        shell_exec('sudo rm -rf '.$patchDir.'/'.$tagBig.'/'.$params[2]);
    }
    echo "补丁安装成功\n";exit;
}else if($params[1] == 'uninstall'){
    if(!file_exists($bakDir.'/'.$tagBig)){
        shell_exec('sudo mkdir -p '.$bakDir.'/'.$tagBig);
        shell_exec('sudo chmod 777 '.$bakDir.'/'.$tagBig);
    }
    $files = [];
    $handler = opendir($bakDir.'/'.$tagBig);
    while (($filename = readdir($handler)) !== false) {
        if ($filename != "." && $filename != "..") {  
                $files[] = $filename ;  
        }  
    }  
    closedir($handler);
    $bakBranch = '';
    if($files){
        foreach ($files as $v) {  
            if(strpos($v,$params[2].'.tar.gz') !== false){
                $bakBranch = $v;
            }
        }
    }
    if($bakBranch){
        chdir($bakDir.'/'.$tagBig);
        shell_exec('sudo tar -zxvf '.$bakBranch);
        $dirname = str_replace('.tar.gz','',$bakBranch);
        //修改patchfile.log
        $logfile = readFileByLine($bakDir.'/'.$tagBig.'/'.$dirname.'/file.log');
        if($logfile){
            foreach ($logfile as $v) {
                foreach ($v as $value) {
                    $str = str_replace('/','\/',$value);
                    $res = shell_exec("sudo sed -n -e '/".$str."/=' ".$patchDir.'/'.$tagBig.'/patchfile.log');
                    $resArr = explode("\n",$res);
                    foreach ($resArr as $key => $val) {
                        if(!$val){
                            unset($resArr[$key]);
                        }
                    }
                    shell_exec("sudo sed -i '".$resArr[count($resArr) - 1]."d' ".$patchDir."/".$tagBig."/patchfile.log");
                }
            }
        }
        shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname.'/file.log');
        $str = 'sudo cp -Rf '.$bakDir.'/'.$tagBig.'/'.$dirname.'/* '.$codeDir.'/';
        shell_exec($str);
        if(file_exists($bakDir.'/'.$tagBig.'/'.$bakBranch)){
            shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$bakBranch);
        }
        if(file_exists($bakDir.'/'.$tagBig.'/'.$dirname)){
            shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname);
        }
        echo "补丁回滚成功\n";exit;
    }else{
        echo "补丁回滚失败,对应补丁备份文件不存在\n";exit;
    }
}else if($params[1] == 'install' && $params[2] == '-f'){
    //修改forceinstall为install -f,将$params[3]赋值给$params[2]
    $params[2] = $params[3];
    //获取分支tag
    $tagInArr   = explode('-',$params[2]);
    $tag        = $tagInArr[0];
    //获取tag对应大版本
    $tagArr     = explode('.',$tag);
    $tagBig     = implode('.',[$tagArr[0],$tagArr[1],$tagArr[2]]);
    if(!file_exists($patchDir)){
        echo "补丁包目录不存在,请先执行补丁下载操作\n";exit;
    }
    $file_path = $patchDir.'/'.$tagBig.'/'.$params[2].'.tar.gz';
    if(!file_exists($file_path)){
        echo "补丁包不存在,请先执行补丁下载操作\n";exit;
    }
    chdir($patchDir.'/'.$tagBig);
    shell_exec('sudo tar -zxvf '.$params[2].'.tar.gz');
    $filenames = get_filenamesbydir($patchDir.'/'.$tagBig.'/'.$params[2]);
    $bakfiles  = [];
    foreach ($filenames as $v) {
        $bakfile    = str_replace($patchDir.'/'.$tagBig.'/'.$params[2].'/','',$v);
        $bakfiles[] = $bakfile;
    }
    //修改patchfile.log
    $filelog = readFileByLine($patchDir.'/'.$tagBig.'/'.$params[2].'/file.log');
    if($filelog){
        foreach ($filelog as $v) {
            foreach ($v as $value) {
                shell_exec("sudo echo '".$value."' >> ".$patchDir."/".$tagBig."/patchfile.log");
            }
        }
    }
    $dirname  = date('YmdHi',time()).'-'.$params[2];
    $tarname  = $dirname.'.tar.gz';
    if(file_exists($bakDir.'/'.$tagBig.'/'.$dirname)){
        shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname);
    }
    shell_exec('sudo mkdir -p '.$bakDir.'/'.$tagBig.'/'.$dirname);
    shell_exec('sudo chmod 777 '.$bakDir.'/'.$tagBig.'/'.$dirname);
    chdir($codeDir);
    foreach ($bakfiles as $v) {
        if(file_exists($v)){
            shell_exec('sudo cp --parents '.$v.' '.$bakDir.'/'.$tagBig.'/'.$dirname.'/');
        }
    }
    shell_exec('sudo mv '.$patchDir.'/'.$tagBig.'/'.$params[2].'/file.log '.$bakDir.'/'.$tagBig.'/'.$dirname.'/file.log');
    chdir($bakDir.'/'.$tagBig);
    shell_exec("sudo tar -zcvf ".$tarname." ".$dirname);
    if(file_exists($bakDir.'/'.$tagBig.'/'.$dirname)){
        shell_exec('sudo rm -rf '.$bakDir.'/'.$tagBig.'/'.$dirname);
    }
    $str = "sudo cp -R ".$patchDir."/".$tagBig."/".$params[2]."/* ".$codeDir.'/';
    shell_exec($str);
    if(file_exists($patchDir.'/'.$tagBig.'/'.$params[2])){
        shell_exec('sudo rm -rf '.$patchDir.'/'.$tagBig.'/'.$params[2]);
    }
    echo "补丁安装成功\n";exit;
}

//发送文件到补丁服务器
function _sendRequest($url, $request = 'GET', $post = array(), $timeout = 10)
{
    if (!$url) return '';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); //设置访问的url地址
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //设置超时
    curl_setopt($ch, CURLOPT_USERAGENT, 'NFSS Client'); //用户访问代理 User-Agent
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request); //使用一个自定义的请求信息来代替"GET"或"HEAD"作为HTTP请求
    curl_setopt($ch, CURLOPT_POST, 1); //指定post数据
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); //添加变量
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //返回结果
    if (stripos($url, 'https:') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}
//列出目录下文件
function get_allfiles($path,&$files)
{
    if(is_dir($path)){
        $dp = dir($path);
        while ($file = $dp ->read()){
            if($file !="." && $file !=".." && $file != "file.log"){
                get_allfiles($path."/".$file, $files);
            }
        }
        $dp ->close();
    }
    if(is_file($path)){
        $files[] =  $path;
    }
}
function get_filenamesbydir($dir)
{
    $files =  array();
    get_allfiles($dir,$files);
    return $files;
}
//按行读取文件
function readFileByLine($filename)  
{  
    $fh = fopen($filename, 'r');
    $files = [];     
    while (! feof($fh)) {  
        $line = fgets($fh);  
        if($line){
            $lineArr = explode(' ',$line);
            $files[trim($lineArr[0])][] = trim($line);
        }  
    }  
    fclose($fh);
    return $files;  
}
