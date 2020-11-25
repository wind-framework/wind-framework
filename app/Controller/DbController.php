<?php

namespace App\Controller;

use Amp\Promise;
use Framework\Base\Controller;
use Framework\Db\Db;
use Framework\View\ViewInterface;

class DbController extends Controller
{

    public function soul(ViewInterface $view)
    {
        $count = yield Db::table('soul')->count();
        $offset = mt_rand(0, $count-1);
        $row = yield Db::table('soul')->limit(1, $offset)->fetchOne();

        if ($row) {
            Db::table('soul')->where(['id' => $row['id']])->update(['^hits'=>'hits+1']);
            return $view->render('soul.twig', ['title'=>$row['title']]);
        } else {
            return "今天不丧。";
        }
    }

    public function soulFind($id)
    {
        $row = yield Db::table('soul')->where('id', $id)->fetchOne();

        if ($row) {
            return print_r($row, true);
        } else {
            return "无该丧。";
        }
    }

    public function concurrent()
    {
        $a = Db::execute("SELECT SLEEP(3)");
        $b = Db::execute("SELECT SLEEP(3)");

        yield Promise\all([$a, $b]);

        return 'concurrent';
    }

}