<?php
/**
 * Error.php
 * Create on 2021/7/29 17:56
 * 异常输出类
 */

namespace ms\core;

class Error
{
    public static function echoSqlError($e, $sql = '')
    {
        $HTML = '<!doctype html>
                 <html>
                  <head>
                    <meta charset="utf-8">
                    <title>错误提示</title>
                    <style>
                       .container { max-width: 100%; background-color: #f0f0f0; padding: 2% 5%; border-radius: 10px }
                       ul { padding-left: 20px; background: #ffffff; font-size: 12px; }
                       ul li { line-height: 2.3 }
                       a { color: #20a53a }
                    </style>
                  </head>
                  <body>
                    <div class="container">
                        <h1>{$title}</h1>
                        <h5>错误位置：{$file}</h5>
                        <h5>错误SQL:{$sql}</h5>
                        {$list}
                    </div>
                   </body>
                  </html>';
        $HTML = str_replace('{$title}', $e->getMessage(), $HTML);
        $listHtml = '';
        foreach ($e->getTrace() as $k => $v) {
            if(isset($v['args'])){
                $args = json_encode($v['args']);
            }else{
                $args = '';
            }
                
            $listHtml .= "<ul>
                <li> 文件：{$v['file']} {$v['line']}</li>
                <li> {$v['class']} {$v['type']} {$v['function']}</li>
                <li> args：{$args} </li>
                </ul>";
        }
        $HTML = str_replace('{$list}', $listHtml, $HTML);
        $HTML = str_replace('{$sql}', $sql, $HTML);
        $HTML = str_replace('{$file}', $e->getFile() .'  '. $e->getLine(), $HTML);
        return $HTML;
    }
}