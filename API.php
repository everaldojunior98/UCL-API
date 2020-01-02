<?php
    header('Content-Type: application/json; charset=utf-8');

    if(isset($_POST['user']) && isset($_POST['pass']))
        echo FetchData($_POST['user'], $_POST['pass']);
    else if(isset($_GET['user']) && isset($_GET['pass']))
        echo FetchData($_GET['user'], $_GET['pass']);

    function FetchData($user, $pass)
    {
        //URLs
        $loginUrl = "https://eies.ucl.br/webaluno/login/?next=/webaluno/";
        $financialUrl = "https://eies.ucl.br/webaluno/financeiro/";
        $notesFrameUrl = "https://eies.ucl.br/webaluno/quadrodenotas/";
        $scheduleUrl = "https://eies.ucl.br/webaluno/horarioindividual/";

        $csrf_token_field_name = "csrfmiddlewaretoken";
        $params = array(
            "user" => $user,
            "password" => $pass
        );
    
        $token_cookie= realpath("cookie.txt");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $token_cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $token_cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) 
            die(curl_error($ch));
    
        libxml_use_internal_errors(true);
        $dom = new DomDocument();
        $dom->loadHTML($response);
        libxml_use_internal_errors(false);
        $tokens = $dom->getElementsByTagName("input");
        for ($i = 0; $i < $tokens->length; $i++) 
        {
            $meta = $tokens->item($i);
            if($meta->getAttribute('name') == 'csrfmiddlewaretoken')
                $t = $meta->getAttribute('value');
        }
    
        if($t)
        {
            $csrf_token = file_get_contents(realpath("csrf_token.txt"));
            $postinfo = "";
            foreach($params as $param_key => $param_value) 
            {
                $postinfo .= $param_key ."=". $param_value . "&";	
            }
            $postinfo .= $csrf_token_field_name ."=". $t;
    
            $headers = array();
    
            $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
            $header[] = "Cache-Control: max-age=0";
            $header[] = "Connection: keep-alive";
            $header[] = "Keep-Alive: 300";
            $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
            $header[] = "Accept-Language: en-us,en;q=0.5";
            $header[] = "Pragma: ";
            $headers[] = "X-CSRF-Token: $t";
            $headers[] = "Cookie: $token_cookie";
            curl_setopt($ch, CURLOPT_URL, $loginUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
            curl_setopt($ch, CURLOPT_COOKIEJAR, $token_cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $token_cookie);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
            curl_setopt($ch, CURLOPT_HTTPheader, $headers);
            curl_setopt($ch, CURLOPT_REFERER, $loginUrl);
            curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 260);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
            ob_start();
            $loginHtml = curl_exec($ch);
            $result = curl_getinfo($ch);
            ob_get_clean();
    
            //Checa se foi efetuado o login
            if(!(array_key_exists("redirect_count", $result) ? $result['redirect_count'] > 0 : false))
                return "E01";//Erro de autenticacao

            //Faz o parse das informações do aluno
            $loginDOM = new DOMDocument();
            $loginDOM->loadHTML($loginHtml);

            //Pega os elementos do slide lateral
            $userDOM = $loginDOM->getElementById('slide-out');

            $info = getElementsByClassName($loginDOM, 'center-align');

            //Seta o nome e a imagem de perfil
            $WebAluno->Nome = preg_split('/$\R?^/m', trim($info[1]->textContent))[0];
            $WebAluno->Imagem = $info[0]->getElementsByTagName('img')[0]->getAttribute('src');

            $info = $userDOM->getElementsByTagName('span');

            $WebAluno->Email = trim($info[0]->textContent);
            $WebAluno->Curso = trim($info[1]->textContent);
            $WebAluno->Matricula = trim(explode(":", $info[2]->textContent)[1]);
            $WebAluno->CR = trim(explode(":", $info[3]->textContent)[1]);

            //FINANCEIRO\\
            //Faz o parse da aba Financeiro
            curl_setopt($ch, CURLOPT_URL, $financialUrl);
            $financesHtml = curl_exec($ch);

            $financesDOM = new DOMDocument();
            $financesDOM->loadHTML($financesHtml);
            
            //FATURAS ABERTAS\\
            $openInvoicesDOM = $financesDOM->getElementById('fin1');

            $header = $openInvoicesDOM->getElementsByTagName('th');
            $lines = $openInvoicesDOM->getElementsByTagName('td');

            //Adiciona os headers no array
            foreach($header as $nodeHeader)
                $openInvoicesHeader[] = trim($nodeHeader->textContent);

            $i = 0;
            $j = 0;

            //Adiciona as linhas no array
            foreach($lines as $line) 
            {
                $tempOpenInvoicesArray[$j][] = trim($line->textContent);
                $i++;
                $j = $i % count($openInvoicesHeader) == 0 ? $j + 1 : $j;
            }

            //Seta as linhas de acordo com a coluna
            for($i = 0; $i < count($tempOpenInvoicesArray); $i++)            
                for($j = 0; $j < count($openInvoicesHeader); $j++)
                    $openInvoicesArray[$i][$openInvoicesHeader[$j]] = $tempOpenInvoicesArray[$i][$j];

            $WebAluno->Financeiro->FaturasEmAberto = $openInvoicesArray;

            //TODAS AS FATURAS\\
            $allInvoicesDOM = $financesDOM->getElementById('fin2');

            $header = $allInvoicesDOM->getElementsByTagName('th');
            $lines = $allInvoicesDOM->getElementsByTagName('td');

            //Adiciona os headers no array
            foreach($header as $nodeHeader) 
                $allInvoicesHeader[] = trim($nodeHeader->textContent);
            
            $i = 0;
            $j = 0;
            
            //Adiciona as linhas no array
            foreach($lines as $line) 
            {
                $tempAllInvoicesArray[$j][] = trim($line->textContent);
                $i++;
                $j = $i % count($allInvoicesHeader) == 0 ? $j + 1 : $j;
            }

            //Seta as linhas de acordo com a coluna
            for($i = 0; $i < count($tempAllInvoicesArray); $i++)            
                for($j = 0; $j < count($allInvoicesHeader); $j++)
                    $allInvoicesArray[$i][$allInvoicesHeader[$j]] = $tempAllInvoicesArray[$i][$j];
            
            $WebAluno->Financeiro->TodasAsFaturas = $allInvoicesArray;

            //QUADRO DE NOTAS\\
            curl_setopt($ch, CURLOPT_URL, $notesFrameUrl);
            $gradesFrameHtml = curl_exec($ch);

            $gradesFrameDOM = new DOMDocument();
            $gradesFrameDOM->loadHTML($gradesFrameHtml);

            $gradesDOM = $gradesFrameDOM->getElementById('aluno_notas');
            
            $gradesArray = array();
            //Pega todos os periodos cursados
            $currentPeriodId = 0;
            foreach(preg_split('/$\R?^/m', trim(getElementsByClassName($gradesDOM, 'col s12')[0]->textContent)) as $period)
            {
                $period = trim($period);
                if(!empty($period))
                {
                    $id = str_replace('/', '-', $period); 
                    $periodDOM = $gradesFrameDOM->getElementById($id);
                    $disciplineNames = array();

                    //Pega o nome das disciplinas cursadas
                    foreach(getElementsByClassName($periodDOM, 'collapsible-header') as $disciplineName)
                        $disciplineNames[] = trim(str_replace('keyboard_arrow_right', '', $disciplineName->textContent));

                    //Pega a SITUAÇÃO, CARGA HORARIA, FALTAS E MEDIA
                    $disciplineInfo = array();
                    $count = 0;
                    foreach(getElementsByClassName($periodDOM, 'center-align') as $infos)
                    {
						$disciplineInfo[$currentPeriodId][$count]["Professor"] = trim(explode("\n", explode(":", getElementsByClassName($periodDOM, 'collection-item dismissable')[$count]->nodeValue)[1])[0]);
                        
						foreach(preg_split('/$\R?^/m', trim($infos->nodeValue)) as $info)
                            $disciplineInfo[$currentPeriodId][$count][explode(":", trim($info))[0]] = trim(explode(":", $info)[1]);
                        $count++;
                    }
                    
                    $disciplineId = 0;
                    foreach(getElementsByClassName($periodDOM, 'striped') as $discipline)
                    {
                        $gradesByGroupArray = array();
                        $headerArray = array();

                        $header = $discipline->getElementsByTagName('th');
                        $lines = $discipline->getElementsByTagName('td');

                        foreach($header as $nodeHeader) 
                            $headerArray[] = trim($nodeHeader->textContent);

                        $i = 0;
                        $j = 0;
                        foreach($lines as $line) 
                        {
                            $gradesByGroupArray[] = trim($line->textContent);
                            $i++;
                            $j = $i % count($headerArray) == 0 ? $j + 1 : $j;
                        }

                        $allGrades = array();
                        $i = 0;

                        //Separa o array a cada 6 sub arrays
                        foreach($gradesByGroupArray as $grade) 
                        {
                            if($i == 6)
                                $i = 0;
                            else
                            {
                                $allGrades[] = $grade;
                                $i++;
                            }
                        }

                        //Coloca as informações (SITUAÇÃO, CARGA HORARIA, MEDIA) no array da disciplina
                        $gradesArray[$period][$disciplineNames[$disciplineId]] = $disciplineInfo[$currentPeriodId][$disciplineId];
						
                        //Gera um novo array com as novas notas
                        foreach(array_chunk($allGrades, 6) as $grades)
                        {
                            $newGrade = array();
                            $i = 0;
                            foreach($grades as $grade)
                            {
                                $newGrade[$headerArray[$i]] = $grade;
                                $i++;
                            }
							
                            $gradesArray[$period][$disciplineNames[$disciplineId]]['Notas'][] = $newGrade;
                        }
                        $disciplineId++;
                    }
                    $currentPeriodId++;
                }
            }

            $WebAluno->QuadroDeNotas = $gradesArray;
            
            //HORARIO\\
            curl_setopt($ch, CURLOPT_URL, $scheduleUrl);
            $scheduleHtml = curl_exec($ch);

            $schedulePageDOM = new DOMDocument();
            $schedulePageDOM->loadHTML($scheduleHtml);

            //Pega a tabela com os horarios
            $scheduleDOM = $schedulePageDOM->getElementById('aluno_horarios');
            
            $schedulesDOM = getElementsByClassName($scheduleDOM, 'col s12');
                
            //Array com os periodos
            $periods = array_map('trim', explode("\n", preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", trim($schedulesDOM[0]->textContent))));
			
            $schedulesArray = array();
            for($i = 0; $i < count($periods); $i++)
            {
                foreach($schedulesDOM[$i + 1]->getElementsByTagName("ul") as $periodScheduleDOM)
                {
                    $disciplineInfo = array_map('trim', explode("Professor:", $periodScheduleDOM->getElementsByTagName("h5")[0]->textContent));
                    $discipline->Professor = utf8_decode($disciplineInfo[1]);
                    
                    foreach(getElementsByClassName($periodScheduleDOM, 'row') as $row)
                    {
						unset($info);
                        $rowData = array_map('trim', explode("\n", $row->textContent));
                        
                        $info->Dia = utf8_decode($rowData[1]);
                        $info->Horario = $rowData[2];
                        $info->Sala = str_replace("Sala ", "", $rowData[3]);
                        //Libera a memoria alocada
                        $discipline->Aulas[] = $info;
                        unset($info);
                    }

                    $schedulesArray[$periods[$i]][utf8_decode($disciplineInfo[0])] = $discipline;
                    unset($discipline);
                }
            }

            $WebAluno->HorárioIndividual = $schedulesArray;

            return json_encode($WebAluno, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
    }

    //Pega todos os elementos que contem uma classe especifica
    function getElementsByClassName($dom, $ClassName, $tagName = null)
    {
        $Elements = $tagName ? $dom->getElementsByTagName($tagName) : $dom->getElementsByTagName("*");

        $Matched = array();
        for($i = 0; $i<$Elements->length; $i++)
            if($Elements->item($i)->attributes->getNamedItem('class'))
                if(strpos($Elements->item($i)->attributes->getNamedItem('class')->nodeValue, $ClassName) !== false)
                    $Matched[]=$Elements->item($i);

        return $Matched;
    }
?>