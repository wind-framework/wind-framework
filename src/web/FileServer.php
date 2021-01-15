<?php

namespace Wind\Web;

use Wind\Base\Config;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

class FileServer
{

    /**
     * 静态文件输出
     *
     * @param Config $config
     * @param Request $request
     * @param $filename
     * @return void|Response
     */
    public static function sendStatic(Config $config, Request $request, $filename)
    {
        $config = $config->get('server.static_file');
        $path = $config['document_root'].'/'.$filename;

        $response = null;

        if ($config['enable_negotiation_cache'] && is_file($path)) {
            if (!empty($ifModifiedSince = $request->header('if-modified-since'))) {
                $modifiedTime = date('D, d M Y H:i:s',  filemtime($path)) . ' ' . \date_default_timezone_get();
                // 文件未修改则返回304
                if ($modifiedTime === $ifModifiedSince) {
                    $response = new Response(304);
                }
            }
        }

        if ($response === null) {
            $response = (new Response())->withFile($path);
        }

        return $response;
    }

}