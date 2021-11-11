<?php
/**
 * PhpShardingPdo  file.
 * @author linyushan  <1107012776@qq.com>
 * @link https://www.developzhe.com/
 * @package https://github.com/1107012776/PHP-Sharding-PDO
 * @copyright Copyright &copy; 2019-2021
 * @license https://github.com/1107012776/PHP-Sharding-PDO/blob/master/LICENSE
 */

namespace PhpShardingPdo\Test;

use PhpShardingPdo\Common\ConfigEnv;
use PhpShardingPdo\Test\Migrate\Migrate;
use PHPUnit\Framework\TestCase;

$file_load_path = __DIR__ . '/../../../autoload.php';
if (file_exists($file_load_path)) {
    include $file_load_path;
} else {
    $vendor = __DIR__ . '/../vendor/autoload.php';
    include $vendor;
}


/**
 * @method assertEquals($a, $b)
 */
class IntegrationTest extends TestCase
{
    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        ConfigEnv::loadFile('./Config/.env');  //加载配置
    }

    /**
     * 重新构建数据库
     * php vendor/bin/phpunit tests/IntegrationTest.php --filter testBuild
     */
    public function testBuild()
    {
        $res = Migrate::build();
        $this->assertEquals($res, true);
    }

    /**
     * 插入测试
     * php vendor/bin/phpunit tests/IntegrationTest.php --filter testInsert
     */
    public function testInsert()
    {
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $data = [
            'article_descript' => '测试数据article_descript',
            'article_img' => '/upload/2021110816311943244.jpg',
            'article_keyword' => '测试数据article_keyword',
            'article_title' => '测试数据article_title',
            'author' => '学者',
            'cate_id' => rand(1,3),
            'content' => '<p>测试数据</p>\n',
            'content_md' => '3123123',
            'create_time' => "2021-11-08 16:31:20",
            'update_time' => "2021-11-08 16:31:20",
            'user_id' => $this->testUserId(),
        ];
        $data['id'] = $this->testGetId(2);
        $res = $model->insert($data);
        $this->assertEquals(!empty($res), true);
    }

    public function testGetId($stub = 1){
        $autoModel = new \PhpShardingPdo\Test\Model\AutoDistributedModel();
        while (true){
            $resReplaceInto = $autoModel->replaceInto(['stub' => $stub]);
            if(empty($resReplaceInto)){
                usleep(50);
                continue;
            }
            break;
        }
        $this->assertEquals($autoModel->getLastInsertId() > 0,true);
        return $autoModel->getLastInsertId();
    }

    public function testUserId(){
        $model = new \PhpShardingPdo\Test\Model\UserModel();
        $model->startTrans();
        $accountModel = new \PhpShardingPdo\Test\Model\AccountModel();
        $username = 'test_'.date('YmdHis');
        $id = $this->testGetId(1);
        $data = [
            'username' => $username,
            'password' => date('YmdHis'),
            'email' => 'test@163.com',
            'nickname' => '学者',
            'id' => $id,
        ];
        $res = $model->insert($data);
        if(empty($res)){
            $model->rollback();
        }
        $this->assertEquals(!empty($res),true);
        $res = $accountModel->insert([
            'username' => $username,
            'id' => $id
        ]);
        if(empty($res)){
            $model->rollback();
        }
        $this->assertEquals(!empty($res),true);
        $model->commit();
        return $id;
    }

    public function testSelectFind(){
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $info = $model->where([
            'cate_id' => 1
        ])->find();
        $this->assertEquals(!empty($info),true);
    }

    public function testSelectFindAll(){
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $list = $model->where([
            'cate_id' => 1
        ])->findAll();
        $this->assertEquals(!empty($list),true);
    }

    public function testSelectOrderFindAll(){
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $list = $model->where([
            'cate_id' => 1
        ])->order('update_time desc')->findAll();
        $this->assertEquals(!empty($list),true);
    }


    public function testSelectGroupFindAll(){
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $list = $model->where([
            'cate_id' => 1
        ])->group('article_title')->findAll();
        $this->assertEquals(!empty($list),true);
    }

    public function testSelectGroupOrderFindAll(){
        $model = new \PhpShardingPdo\Test\Model\ArticleModel();
        $list = $model->field('article_title,sum(is_choice) as is_choice')->where([
            'cate_id' => 1
        ])->order('article_title desc')->group('article_title')->findAll();
        $this->assertEquals(!empty($list),true);
        print_r($list);
    }


}