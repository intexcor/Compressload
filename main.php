<?php
// Замените 'your_bot_token' на токен вашего бота
const BOT_TOKEN = '<your_bot_token>';

// Запрашиваемые URL API Telegram
const API_URL = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';

// Функция для отправки сообщения с использованием cURL
function sendMessage($chatId, $message): bool|string
{
    $params = [
        'chat_id' => $chatId,
        'text' => $message,
    ];
    $url = API_URL . 'sendMessage';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}



function compressVideo($video_url, $target_video_size): ?string
{
    echo $video_url;
    $video_data = file_get_contents($video_url);

    // Получаем длительность и битрейт аудио из данных видео
    list($duration, $audio_rate) = getDurationAndAudioRateFromBytes($video_data);

    // Переводим целевой размер видео из MB в биты
    $target_video_size_bits = $target_video_size * 1024 * 1024 * 8;

    // Вычисляем общий битрейт (включая аудио) для достижения целевого размера файла
    $total_bitrate_kbps = ($target_video_size_bits / $duration) / 1024;

    // Вычитаем битрейт аудио из общего битрейта для определения битрейта видео
    $video_bitrate_kbps = $total_bitrate_kbps - $audio_rate;

    // Создаем временный файл для записи видеоданных
    $tmp_filename = tempnam(sys_get_temp_dir(), 'video_');
    file_put_contents($tmp_filename, $video_data);

    // Путь и имя файла статистики для первого прохода
    $stats_file = dirname($tmp_filename) . "/ffmpeg_stats.log";
    $output_filename = "compressed_video_" . basename($tmp_filename) . ".mp4";

    // Первый проход для определения статистики
    $pass1_command = "ffmpeg -i $tmp_filename -c:v libx264 -b:v {$video_bitrate_kbps}k -preset ultrafast -tune fastdecode -profile:v main -level 3.1 -pass 1 -an -f mp4 -y -passlogfile $stats_file -threads auto " . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null');
    exec($pass1_command);

    $pass2_command = "ffmpeg -i $tmp_filename -c:v libx264 -b:v {$video_bitrate_kbps}k -preset ultrafast -tune fastdecode -profile:v main -level 3.1 -pass 2 -c:a aac -b:a {$audio_rate}k -passlogfile $stats_file -threads auto $output_filename";
    exec($pass2_command, $pass2_output, $pass2_status);

    // Удаление временного файла
    unlink($tmp_filename);

    if ($pass2_status === 0) {
        echo "Видео успешно сжато.\n";
        return $output_filename;
    } else {
        echo "Ошибка при сжатии видео.\n";
        return null;
    }
}

function getDurationAndAudioRateFromBytes($video_data): array
{
    // Создаем временный файл для записи видеоданных
    $tmp_filename = tempnam(sys_get_temp_dir(), 'video_');
    file_put_contents($tmp_filename, $video_data);

    $result = getDurationAndAudioRate($tmp_filename);

    // Удаление временного файла
    unlink($tmp_filename);

    return $result;
}

function getDurationAndAudioRate($file_path): array
{
    // Получаем длительность видео в секундах
    $duration = exec("ffprobe -v error -show_entries format=duration -of csv=p=0 $file_path");

    // Получаем битрейт аудио в KiB/s
    $audio_rate = exec("ffprobe -v error -select_streams a:0 -show_entries stream=bit_rate -of csv=p=0 $file_path");
    $audio_rate = intval($audio_rate) / 1024; // Преобразуем в KiB/s

    return array(floatval($duration), $audio_rate);
}


function checkMessage($chat_id, $text): void
{
    if (explode(' ', $text)[0] == '/start'){
        $start_text = "🤖 Привет!

Отправь мне видео или ссылку на YouTube
Я бесплатно уменьшу размер до 20MB и пришлю видео";
        sendMessage($chat_id, "$start_text");
    }


    elseif (str_contains($text, 'youtube.com') || str_contains($text, 'youtu.be')) {
        sendMessage($chat_id, "Видео скачивается");
        $video_url = escapeshellarg($text);
        $filename = 'filenames_' . $chat_id . '.txt'; // Файл имен для каждого пользователя
        $download_command = "youtube-dl -o '%(title)s.%(ext)s' -f 18 $video_url --exec \"echo>$filename\"";
        shell_exec($download_command); // Загружаем видео и регистрируем имя файла

// Читаем имя файла из файла
        $video_file = trim(file_get_contents($filename));
        unlink($filename);

// Проверяем, что файл существует
        if (!empty($video_file)) {
            sendMessage($chat_id, "Видео успешно загружено.");
            $file_size = filesize($video_file); // Размер файла в байтах
            $max_file_size = 19 * 1024 * 1024; // 19 МБ в байтах

            if ($file_size > $max_file_size) {
                sendMessage($chat_id, "❌ Размер вашего видео больше 19 МБ\nСжимаем видео перед отправкой...");
                // Сжимаем и отправляем видео
                sendMessage($chat_id, "Видео сжимается");
                $compressed_video = compressVideo($video_file, 19); // 20 MB

                if ($compressed_video) {
                    sendMessage($chat_id, "Видео отправляется");
                    sendVideo($chat_id, $compressed_video);
                    unlink($compressed_video);
                } else {
                    sendMessage($chat_id, "Ошибка при сжатии видео.");
                }

                // Удаляем временный файл скачанного видео
            } else {
                // Если размер меньше 19 МБ, отправляем видео пользователю
                sendMessage($chat_id, "Отправляем видео...");
                sendVideo($chat_id, $video_file);
                // Удаляем временный файл скачанного видео
            }
            unlink($video_file);
        } else {
            sendMessage($chat_id, "Не удалось найти загруженное видео.");
        }
    }
    else {
        sendMessage($chat_id, "❌ Я принимаю только ссылки на YouTube

Нужна помощь? Напишите в поддержку @melnichuk_artem и мы решим вопрос");
    }
}
// Функция для получения обновлений с использованием Long Polling
function getUpdates($offset) {
    $offset = (int)$offset + 1;
    $url = API_URL . 'getUpdates?offset=' . $offset . '&timeout=60'; // Используем Long Polling с таймаутом 60 секунд
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}


function sendVideo($chat_id, $video_file): bool|string
{
    $url = API_URL . 'sendVideo';
    $params = [
        'chat_id' => $chat_id,
        'video' => new CURLFile($video_file),
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}


// Главный цикл бота
$update_id = 0;
while (true) {
    $updates = getUpdates($update_id)['result'];
    foreach ($updates as $update) {
        // Проверяем наличие сообщения в текущем обновлении
        if (!isset($update['message'])) {
            continue;
        }
        // Получаем информацию о сообщении
        $message = $update['message'];
        $update_id = $update["update_id"];
        $chat_id = $message['chat']['id'];
        // Проверяем наличие текста в сообщении
        if (isset($message['text'])) {
            $text = $message['text'];
            checkMessage($chat_id, $text);
        }
    }
}
