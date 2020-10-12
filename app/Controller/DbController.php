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
        $row = yield Db::fetchOne("SELECT * FROM soul ORDER BY RAND() LIMIT 1");

        if ($row) {
            Db::execute("UPDATE soul SET hits=hits+1 WHERE `id`=?", [$row['id']]);
            return $view->render('soul.twig', ['title'=>$row['title']]);
        } else {
            return "今天不丧。";
        }
    }

    public function soulFind($context)
    {
        $id = $context->get('vars')['id'];

        $result = yield Db::query("SELECT * FROM soul WHERE `id`=?", [$id]);

        if (yield $result->advance()) {
            $row = $result->getCurrent();
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