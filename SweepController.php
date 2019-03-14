<?php

namespace App\Http\Controllers\ProductLibrary;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SweepController extends Controller{

    private $maxX = 9;
    private $maxY = 9;
    private $flopTable = [];
    public function manage(Request $request){
        if (!$request->ajax()){
            for($i=1; $i <= $this->maxX; $i++){
                for ($j=1; $j <= $this->maxY; $j++){
                    $this->flopTable[$i][$j] = 0;
                }
            }
            return view('ProductLibrary.sweep2',[
                'flopTable'=>json_encode($this->flopTable)
            ]);
        }
        $clickX = rand(1,9); $clickY = rand(1,9);
        $table = $this->getBombTable($clickX,$clickY);
        return response()->json($table);
    }

    public function sweep(Request $request){
        $x = intval($request->input('x'));
        $y = intval($request->input('y'));
        $table = $request->input('table');
        $this->flopTable = json_decode($request->input('flopTable'), true);

        $this->flop($table, $x, $y);
        return response()->json($this->flopTable);
        dd($this->flopTable);
    }


    public function flop($table, $x, $y){

        static $flopPos;
        $sidePos = $this->getSideFlopPos($x, $y);

        foreach ($sidePos as $pos){
            $flopPos[] = $pos;
            dump($flopPos);
            if ($table[$pos[0]][$pos[1]] === 'bomb'){
                $this->flopTable[$pos[0]][$pos[1]] = false;
            }elseif ($table[$pos[0]][$pos[1]] === '0'){
                $this->flopTable[$pos[0]][$pos[1]] = true;
                if (!in_array($pos, $flopPos)) {
                    $this->flop($table, $pos[0], $pos[1]);
                }
            }else{
                $this->flopTable[$pos[0]][$pos[1]] = true;
            }
        }

        return true;

    }

    public function getSideFlopPos($x, $y){
        $sideClickPos = [];
        for($i = -1; $i <= 1; $i++){
            for ($j = -1; $j <= 1; $j++){
                if ($x+$i > $this->maxX || $x+$i == 0 || $y+$j > $this->maxY || $y+$j == 0 )
                    continue;

                $sideClickPos[] = [$x+$i, $y+$j];
            }
        }
        return $sideClickPos;
    }

    public function getRightItem($table,$clickX, $clickY){
        static $right = [];
        $clickX++;
        $right[] = [$clickX, $clickY];
        if (isset($table[$clickX][$clickY]) && $table[$clickX][$clickY] === 0)
            $this->getRightItem($table, $clickX, $clickY);
        return $right;
    }

    public function getDownItem($table,$clickX, $clickY){
        static $down = [];
        $clickY++;
        $down[] = [$clickX, $clickY];
        if (isset($table[$clickX][$clickY]) && $table[$clickX][$clickY] === 0)
            $this->getDownItem($table, $clickX, $clickY);
        return $down;
    }

    public function getClickSidePos($clickX, $clickY, $maxX){
        for($i = -1; $i <= 1; $i++){
            for ($j = -1; $j <= 1; $j++){
                if ($clickX+$i > $maxX || $clickX+$i == 0 || $clickY+$j > $maxX || $clickY+$j == 0 )
                    continue;
                $sideClickPos[] = [$clickX+$i, $clickY+$j];
            }
        }
        return $sideClickPos;
    }




    public function getBombTable($clickX = 5, $clickY = 5){
        $clickPos = [$clickX, $clickY];
        $bombNum = 10; $maxX = 9; $maxY = 9;
        //生成一个table表格，每个表格的值都为0
        $table = []; $randBomb = [];
        for ($i = 1; $i <= 9; $i++){
            for ($j = 1; $j <= 9; $j++){
                $table[$i][$j] = 0;
                $randBomb[] = [$i, $j];
            }
        }
        //点击的位置以及该位置的附近的八个位置不能有炸弹(为了保证点击的位置一定不会出现数字)
        $sideClickPos = [];
        //获取点击附近的点坐标
        $sideClickPos = $this->getClickSidePos($clickX, $clickY, $maxX);
        foreach ($randBomb as $k=>$pos){
            foreach ($sideClickPos as $sidePos){
                if ($pos === $sidePos) unset($randBomb[$k]);
            }
        }
        //产生炸弹的坐标数组
        shuffle($randBomb);
        $randBomb = array_slice($randBomb,0,$bombNum);
        //将炸弹放入到table中
        foreach ($randBomb as $val){
            $posX = $val[0];
            $posY = $val[1];
            $table[$posX][$posY] = 'bomb';
        }

        //以雷的位置为中心，寻找8个位置如：$table[i, j]找到 $table[i+1, j-1]、$table[i+1, j]、$table[i+1, j+1]
        foreach ($randBomb as $bombPos){
            $posX = $bombPos[0];
            $posY = $bombPos[1];
            //得到每个炸弹旁边的8个位置的
            $sidePos = [];
            for($i = -1; $i <= 1; $i++){
                for ($j = -1; $j <= 1; $j++){
                    if ($j == $i && $j == 0) continue;
                    if ($posX+$i > $maxX || $posX+$i == 0 || $posY+$j > $maxX || $posY+$j == 0 )
                        continue;
                    if ($table[$posX+$i][$posY+$j] === 'bomb') continue;
                    $sidePos[] = [$posX+$i, $posY+$j];
                }
            }
            //遍历$sidePos，给每个位置设置数字
            foreach ($sidePos as $v){
                if ($table[$v[0]][$v[1]] === 'bomb') continue;
                $table[$v[0]][$v[1]]++;
            }
        }
        return $table;
    }
}
