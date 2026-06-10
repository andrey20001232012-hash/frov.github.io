-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Июн 10 2026 г., 16:30
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `flover`
--

-- --------------------------------------------------------

--
-- Структура таблицы `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `login` varchar(255) NOT NULL,
  `passwor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `admin`
--

INSERT INTO `admin` (`id`, `login`, `passwor`) VALUES
(1, '1', '1');

-- --------------------------------------------------------

--
-- Структура таблицы `client`
--

CREATE TABLE `client` (
  `id` int(255) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` int(11) NOT NULL,
  `address` int(100) NOT NULL,
  `comment` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `client`
--

INSERT INTO `client` (`id`, `name`, `email`, `phone`, `address`, `comment`) VALUES
(12, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(13, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(22, 'Валерия', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(23, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(24, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(25, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(28, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(29, 'Вероника', 'pleshmakarov@yandex.ru', 2147483647, 0, 0),
(30, 'Андрей', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(31, 'Валерия', 'aana@mama.ru', 2147483647, 0, 0),
(32, 'Валерия', 'aana@mama.ru', 2147483647, 0, 0),
(33, 'Валерия', '112212121', 21321312, 0, 0),
(34, 'Иван', 'mam@ma.ru', 2147483647, 0, 0),
(35, 'АНл', 'andrey20001232012@gmail.com', 2147483647, 0, 0),
(36, 'Андрей', 'aana@mama.ru', 2147483647, 0, 0),
(37, 'фыв', 'aana@mama.ru', 2147483647, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `flovers`
--

CREATE TABLE `flovers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(11,0) NOT NULL,
  `image` varchar(255) NOT NULL,
  `categori` text NOT NULL,
  `kol_vo` int(250) NOT NULL,
  `opisanie` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `flovers`
--

INSERT INTO `flovers` (`id`, `name`, `price`, `image`, `categori`, `kol_vo`, `opisanie`) VALUES
(1, 'Букет роз', 3500, 'roze1.jpg', 'Букет', 0, 'Букет из более чем 50 алых роз в форме сердца — символ страсти и любви. Прозрачная упаковка с красной лентой подчёркивает торжественность момента. Идеален для Дня влюблённых, годовщины или признания в чувствах.'),
(2, 'Кустовая роза', 5000, 'while_roze.jpg', 'Букет', 0, 'Нежный букет из кремово-розовых пионовидных роз, упакованный в светло-розовую бумагу с бантом. Элегантный выбор для поздравления с днём рождения, свадьбы или другого важного события. '),
(3, 'Букет хризантем', 4500, 'hrizantem.jpg', 'Букет', 0, 'Легкий букет из белых и нежно-розовых гвоздик, дополненный зеленью и светлой упаковкой с бантами. Нежный, воздушный вариант для поздравлений, свиданий или уютных вечеров.  '),
(5, 'Кустовая матиола', 500, 'matiola.jpg', 'Цветы', 30, 'Матиола — ночной душистый цветок семейства крестоцветных. Невысокий куст с мелкими фиолетовыми или белыми цветками, источающими сильный аромат вечером. Используется в садах для аромата и привлекает бабочек.'),
(6, 'Альстромерия', 250, 'alstromeria_flauwer.jpg', 'Цветы', 25, 'Яркие цветы, похожие на львиный зев. Они имеют насыщенные оттенки красного, розового, белого и жёлтого цветов, создавая красочный букет.'),
(8, 'Ромашки с матиолами', 2500, 'buket_romahek_s_motioloy.jpg', 'Букет', 5, 'Букет состоит из нежных розовых цветов и маленьких белых ромашек, собранных в пышную композицию, обернутую прозрачной упаковкой.'),
(9, 'Бумага фамеран', 300, 'bumaga_fameran.jpg', 'Аксессуар', 30, 'Представлены листы цветной бумаги пастельных оттенков, свернутые в рулоны. Цвета включают красный, бежевый, персиковый, розовый, сиреневый, голубой и зеленый.'),
(12, 'Бумага глянсевая', 300, 'bumaga_glinsewai.jpg', 'Аксессуар', 12, 'Представлена бумага с рукописным текстом, свернутая в рулон. Бумага имеет белый и светло-розовый оттенок, а текст написан чернилами.'),
(14, 'Бумага прозрачная', 300, 'bumaga_prozraachnay.jpg', 'Аксессуар', 45, 'Листы бумаги пастельных оттенков, уложенные друг на друга. Цвета варьируются от нежно-розового до кремового и темно-коричневого. Края листов украшены золотистой окантовкой.'),
(16, 'Бумага сетка', 300, 'bumaga_setka.jpg', 'Аксессуар', 19, 'Сетка голубого цвета, свернутая в рулон. Она имеет мелкую ячейковую структуру и выглядит легкой и полупрозрачной.'),
(17, 'Бумага пастельная ', 300, 'bymaga_glansewai.jpg', 'Аксессуар', 0, 'Бумага различных пастельных оттенков. Бумага имеет гладкую текстуру и мягкие тона, включая розовый, бирюзовый, мятный, персиковый и кремовый.'),
(18, 'Дельфиниум с подсолнухами', 5000, 'delfinium_s_podsolnuxow_korzina.jpg', 'Корзинка', 0, 'Композиция из натуральных цветов, включающая синие гортензии, зеленые хризантемы, подсолнухи и декоративные травы.'),
(22, 'Дельфиниум', 750, 'delfiniym_flower.jpg', 'Цветы', 40, 'Букет состоит из крупного дельфиниума насыщенного фиолетово-лилового оттенка. Стебли собраны вместе и перевязаны белой лентой, придавая композиции аккуратный и элегантный вид.'),
(23, 'Эустома', 450, 'eustoma.jpg', 'Цветы', 21, 'Розовые и зеленые эустома. Цветы разного размера, некоторые раскрылись полностью, а другие находятся в бутоне. Бутоны эустома вытянутые и изящные, расположены вдоль длинных зеленых стеблей.'),
(24, 'Гербера с матиолой', 3500, 'gerbera_smatioloy_buket.jpg', 'Букет', 2, 'Яркий букет включает герберы оранжевого, розового и желтого цветов, синие дельфиниумы и белые ромашки. Дополняют композицию зеленые листья и травы, создавая живой и естественный образ.'),
(25, 'Гербера', 350, 'gerbera_flower.jpg', 'Цветы', 14, 'гербера различных оттенков: красные, розовые, желтые и оранжевые. У каждого цветка крупные лепестки, окружающие ярко выраженную центральную часть. Листья и стебли зеленого цвета.'),
(26, 'Гербера с зеленью', 2550, 'gerbera_s_zelenu_buket.jpg', 'Букет', 1, 'Букет из розовых гербер с крупными лепестками и контрастными центрами. Добавляют разнообразие мелкие белые ромашки и зелень.'),
(27, 'Гвоздика с тюльпанами', 4500, 'gwozdika_stulpanami_y_irisami_korzina.jpg', 'Букет', 2, 'Букет состоит из белых цветков с желтой серединкой, зеленых пионов, оранжевых тюльпанов, экзотического растения с длинными листьями и мелких белых цветов.'),
(28, 'Гвоздика с дерберой ', 3000, 'gwozdiki_s_gerberoi.jpg', 'Букет', 0, 'Букет состоит из розовых гвоздик и белых гербер с зеленовато-желтыми сердцевинами. Цветы упакованы в белую бумагу, перевязанную лентой.'),
(29, 'Ирис', 500, 'iris_flower.jpg', 'Цветы', 32, 'фиалковые ирисы с ярко-жёлтыми пятнами на нижних лепестках. Цветы собраны плотно, стебли зелёные, листья острые и длинные.'),
(30, 'Кустовая гвоздика', 230, 'Kustovayi_gvozdika_flower.jpg', 'Цветы', 12, 'Розовая гвоздика с небольшими бутонами. Стебли зелёные, видны листочки.');

-- --------------------------------------------------------

--
-- Структура таблицы `status_zakaza`
--

CREATE TABLE `status_zakaza` (
  `id` int(11) NOT NULL,
  `id_zakaz_flover` int(11) NOT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `status_zakaza`
--

INSERT INTO `status_zakaza` (`id`, `id_zakaz_flover`, `status`) VALUES
(1, 11, 'на проверке'),
(2, 22, 'Новый'),
(3, 12, 'на проверке'),
(4, 13, 'на проверке'),
(5, 1, 'Готов к доставке'),
(6, 2, 'на проверке'),
(7, 3, 'на проверке'),
(8, 5, 'В обработке'),
(9, 4, 'на проверке'),
(10, 5, 'на проверке'),
(11, 6, 'на проверке'),
(12, 7, 'на проверке'),
(13, 8, 'на проверке'),
(14, 9, 'на проверке'),
(15, 10, 'на проверке'),
(16, 11, 'на проверке');

-- --------------------------------------------------------

--
-- Структура таблицы `zakaz`
--

CREATE TABLE `zakaz` (
  `id` int(11) NOT NULL,
  `id_zakaz_flover` int(11) NOT NULL,
  `id_client` int(11) NOT NULL,
  `summ` int(11) NOT NULL,
  `time` time NOT NULL,
  `date` date NOT NULL,
  `buket` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `zakaz`
--

INSERT INTO `zakaz` (`id`, `id_zakaz_flover`, `id_client`, `summ`, `time`, `date`, `buket`) VALUES
(1, 1, 25, 17000, '11:00:00', '2026-05-27', ''),
(2, 3, 28, 8800, '15:00:00', '2026-05-28', ''),
(3, 5, 29, 8800, '13:00:00', '2026-05-28', ''),
(4, 7, 30, 5800, '15:00:00', '2026-05-31', 'нет'),
(5, 9, 31, 3600, '12:00:00', '2026-06-05', 'нет'),
(6, 11, 32, 4800, '16:00:00', '2026-06-06', 'нет'),
(7, 12, 33, 900, '10:00:00', '2026-06-19', 'нет'),
(8, 13, 34, 3300, '00:00:00', '2026-06-09', 'нет'),
(9, 14, 35, 3150, '00:00:00', '2026-06-09', 'да'),
(10, 15, 36, 2850, '00:00:00', '2026-06-09', 'нет'),
(11, 16, 37, 600, '13:00:00', '2026-06-26', 'нет');

-- --------------------------------------------------------

--
-- Структура таблицы `zakaz_flover`
--

CREATE TABLE `zakaz_flover` (
  `id` int(255) NOT NULL,
  `id_flover` int(255) NOT NULL,
  `kol_vo` int(255) NOT NULL,
  `sum` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `zakaz_flover`
--

INSERT INTO `zakaz_flover` (`id`, `id_flover`, `kol_vo`, `sum`) VALUES
(1, 1, 2, 7000),
(2, 2, 2, 10000),
(3, 1, 1, 3500),
(4, 2, 1, 5000),
(5, 1, 1, 3500),
(6, 2, 1, 5000),
(7, 18, 1, 5000),
(8, 29, 1, 500),
(9, 16, 1, 300),
(10, 28, 1, 3000),
(11, 27, 1, 4500),
(12, 12, 2, 600),
(13, 28, 1, 3000),
(14, 26, 1, 2550),
(15, 26, 1, 2550),
(16, 12, 1, 300);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `flovers`
--
ALTER TABLE `flovers`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `status_zakaza`
--
ALTER TABLE `status_zakaza`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `zakaz`
--
ALTER TABLE `zakaz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_client` (`id_client`);

--
-- Индексы таблицы `zakaz_flover`
--
ALTER TABLE `zakaz_flover`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_flover` (`id_flover`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `client`
--
ALTER TABLE `client`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT для таблицы `flovers`
--
ALTER TABLE `flovers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `status_zakaza`
--
ALTER TABLE `status_zakaza`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `zakaz`
--
ALTER TABLE `zakaz`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `zakaz_flover`
--
ALTER TABLE `zakaz_flover`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `zakaz`
--
ALTER TABLE `zakaz`
  ADD CONSTRAINT `zakaz_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `client` (`id`);

--
-- Ограничения внешнего ключа таблицы `zakaz_flover`
--
ALTER TABLE `zakaz_flover`
  ADD CONSTRAINT `zakaz_flover_ibfk_1` FOREIGN KEY (`id_flover`) REFERENCES `flovers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
