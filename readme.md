## patch.php补丁管理工具使用方法

#### 一、补丁制作上传部分,主要是开发或测试人员操作

##### 环境准备
  
开发或测试人员创建补丁时需要此预备环境,开发人员可在自己的开发环境搭建,测试人员可以在测试服务器搭建

  1. 登录对应服务器,服务器需安装git和php,并且能访问到代码仓库,创建目录/patch    
    
    ```
    mkdir -p /patch
    
    ```

  2. 在/patch目录克隆媒体云代码
    
    ```
    cd /patch
    git clone git@git.meitiyun.org:Mediacloud/mediacloud.git
    ```

  3. 在/patch目录克隆补丁管理工具代码,并将patch.php文件移动到/patch目录下
    
    ```
    cd /patch
    git clone git@git.meitiyun.org:automate/autopatch.git
    mv /patch/autopatch/patch.php /patch/
    ```

##### 补丁制作上传操作

  - 创建补丁

    开发或测试人员登录对应服务器,运行patch.php文件,传三个参,方法create,以及对应补丁分支名和需要对比的分支,例:
    
    ```
    php /patch/patch.php create v1.5.2-fixbug-recommendlist-stick v1.5.2
    ```
    
    生成的补丁包临时存放在/patch目录下,例:/patch/v1.5.2-fixbug-recommendlist-stick.tar.gz
    
    补丁包包含对应分支和tag对比后的不同文件以及一个log.file文件用于记录对应文件最后一次的修改信息

  - 上传补丁

    开发或测试人员登录对应服务器,运行patch.php文件,传两个参,方法put,以及对应补丁分支名,例:
      
    ```
    php /patch/patch.php put v1.5.2-fixbug-recommendlist-stick
    ```
    
    补丁包会上传到补丁服务器对应目录,例如:/data/www/patch.cmstop.com/mediacloud/v1.5.2/v1.5.2-fixbug-recommendlist-stick.tar.gz,同时删除本地临时存放的补丁包

#### 二、补丁下载安装部分,主要是项目运维人员操作

##### 环境准备
  
  项目运维人员安装补丁时需要此预备环境

  1. 登录对应项目服务器,创建目录/patch
    
    ```
    mkdir -p /patch
    ```
    
  2. 在/patch目录克隆补丁管理工具代码,并将patch.php文件移动到/patch目录下
  
    ```
    cd /patch
    git clone git@git.meitiyun.org:automate/autopatch.git
    mv /patch/autopatch/patch.php /patch/
    ```

##### 补丁下载安装回滚操作

  - 下载补丁

	  项目运维人员登录对应项目服务器,运行patch.php文件,传两个参,方法get,以及对应补丁分支名,例:
		
    ```
   	php /patch/patch.php get v1.5.2-fixbug-recommendlist-stick
   	```
   	
    补丁包会下载到对应服务器上对应目录,例如:/data/www/cloud-patch/patch/v1.5.2/v1.5.2-fixbug-recommendlist-stick.tar.gz

  - 安装补丁
	    
    项目运维人员登录对应项目服务器,运行patch.php文件,传两个参,方法install,以及对应补丁分支名,例:
		
    ```
   	php /patch/patch.php install v1.5.2-fixbug-recommendlist-stick
   	```
   	
    安装补丁会备份原文件及对应patch中的file.log用于回退,同时更新对应大版本目录下patchfile.log记录文件修改,然后用补丁包中的文件替换对应代码中的文件

  - 回滚补丁
	  
    项目运维人员登录对应服务器,运行patch.php文件,传两个参,方法uninstall,以及对应补丁分支名,例:
		
    ```
   	php /patch/patch.php uninstall v1.5.2-fixbug-recommendlist-stick
   	```
   	
    回滚补丁会根据备份代码回退,将对应分支修改的文件还原,同时更新patchfile.log文件,然后删除对应备份代码

  - 强制打补丁
	    
    强制打补丁,用于补丁包文件冲突的情况,后加入的补丁包要基于前补丁包检出修改分支,再强制打补丁上去
    项目运维人员登录对应服务器,运行patch.php文件,传三个参,方法install,-f,以及对应补丁分支名,例:
		
    ```
   	php /patch/patch.php install -f v1.5.2-fixbug-recommendlist-stick
   	```
