-- Готовый импорт незаключенок из файла НЕЗАКЛЮЧЕНКИ 2025.xlsx

-- При необходимости замени account_id / created_by_user_id / updated_by_user_id ниже.

START TRANSACTION;

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Пирогово Полевая 67а', 'считаем. ранее работала только с сок', NULL, 'Зорин',
  NULL, 'петрова', 'недозвонились, написали в ватсап. связь в пн-инфа от Саши-с заказчиком еще на замере договорились.', NULL,
  NULL, 'По этому на связи, вчера звонил.
Сегодня буду снова просчитывать вместе с освещением. 23.08 новый просчет', 'xlsx_ready_sql', 'd2305d6c14a1ee4d2cdeec6b744c195bfbd7cb70',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Старомихайловское Звездная 6', 'проект просчет в ткани', NULL, 'Зорин',
  NULL, 'ламохина', 'Смету отправил, пока не ответил. Трубки не берет. ОН в командировке, позвонить в пятницу в 14:00 с заказчиком на связи он дал номер Юлии какой-то. С ней созвонился, сейчас я должен отвезти образцы по потолку. Саша еще общается с дизайнером', NULL,
  NULL, '10.09 отвез образцы заказчику, далее отпишет. Решения нет связь 10.10 заппросили инфу у Зорина посмотреть ответ. 11.10 дизайнер не отвечает, на неделю двинем связь. недозвон дизайнеру перенос на 23.10. Связь на прямую с Сашей', 'xlsx_ready_sql', '96300eff7021c4c738b6e79f8c137a3be7e8179e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Карла Маркса 259/2-211', 'Шумка Мужа на месте не было, дистанционнопросчитал ему, стоимость озвучил. Повторная связь вечером после 17:00', NULL, 'Зорин',
  NULL, 'черпакова', '09.10 связь', '2025-11-03',
  NULL, '10.10 заппросили инфу у Зорина посмотреть ответ. 11.10 пока рано, ещё мебель будет устанавливать прежде. Шумка больше не нужна', 'xlsx_ready_sql', '9253d486c8f9a65afcddaaf985d38573376d2d02',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'СНТ Маяк 17 улица 982', 'к повторная связь завтра.
Хотят  подъехать в офис сразу по освещению просчитать.', NULL, 'Зорин',
  NULL, 'ламохина', '3.11 узнать у саши итог', NULL,
  NULL, 'От Саши не берет трубку, с нас тоже. Написала на ва 04.11 - посмотреть ответ 89120039102 - сказала сама напишет когда решит и просит расчитать треки, инфу передали Саше.', 'xlsx_ready_sql', 'df78cba0c62897a3015976d42cece884e1bbe022',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Проспект Калашникова 23-94', 'сегодня с мужем обсудят, связь на 5.11', NULL, 'Зорин',
  NULL, 'зуева', 'на входящие блок, написали на ва', '2026-05-04',
  NULL, 'не отв, 05.11 написала на ва - посмотреть ответ 89036650001-после НГ установка. , 12.01 сброс, макс нет, перезвонила, актуально будет в марте 04.03 неактуально, отложили на неопределенный срок', 'xlsx_ready_sql', '51d32500b2f078c45ad64ed3bccf3cf891ab66db',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Красная 118-31', 'до потолков пока что рано, поставить связь на 05 декабря', NULL, 'Зорин',
  NULL, 'соковикова', 'трубку не взял. Написали в востапе, связь ставлю на 05.12', '2026-03-10',
  NULL, '05.12 еще ремонт идет, скорее всего, после НГ, позвонит сам по телефону на визитке, 25.12-пока не актуально, попробовать после НГ, макс нет, 15.01 не берет, 19.01 передаю Саше, если будет инфа, даст знать', 'xlsx_ready_sql', '41c9d1d280507dc1d47748876a2b11f2c34fa05d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Цветочная 12/7-116', 'выезда не было, заказчица сказала, что выезжать рано, расчет по проекту', NULL, 'Зорин',
  NULL, 'Дубовцева', NULL, NULL,
  NULL, NULL, 'xlsx_ready_sql', '3e4684680d1139b386145acaf156dd71b6cbfff8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Двигатель Прудовая 16а', 'монтаж примерно в январе, проконсультировал, по освещению подсказал, в офис пригласил, свяжутся с нами самостоятельно', NULL, 'Зорин',
  NULL, 'буторина', 'хороший парень,  10 баллов, все отлично рассказал,. никуда не торопился пока не готовы назначить дату монтажа, еще много работы, связь согласовали на 25.12', '2026-06-01',
  NULL, '25.12 не берет, наисала в макс-не отвечает, 26.12 сказал, что будут смотреть побежит ли весной крыша, попробовать набрать летом', 'xlsx_ready_sql', '060d8c474ddebe585fa6e889633faa4d1d62af0d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Чехова 46-52', 'потолки нужны ближе к февралю, ничего не считал, не мерял, консультация', NULL, 'Зорин',
  NULL, 'керенцева', 'связь 10.02.2026', NULL,
  NULL, 'звонил КЦ 10.02 пока рано. Связь просил конец марта', 'xlsx_ready_sql', 'b59c3c556214639719153ce4df1b9487edda8bce',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Репина 35а/1-71', 'потолки планирует после установки мебели примерно в феврале. Сейчас хотели примерную стоимость узнать. Сейчас согласовывают размеры кухни шкафов. Предложил заключить договор и сделать только черновые работы. Повторная связь на вторник', NULL, 'Зорин',
  NULL, 'петрова', 'инфа получена утром с адреса', NULL,
  NULL, '09.12 договорились созвониться в четверг, пока думают, возможно, будут корректировки вносить, пока мебель не собрана, до НГ договорились созвониться, может договор заключат. Адрес от Лагранс, возможно, перейдет Косте. Костя сказал, что не поедет туда, Саше нужно озвучить сумму, уточняю у Саши 29.12 Позвонить клиенту 05.01 и назначить повторный замер, 08.01 сказала, что кухня будет в феврале, пока нечего даже мерить. Звонили 17.02, линия занята, попробовть еще раз. Звонил КЦ 19.02 - кухня еще не собрана, попросила связь конец марта', 'xlsx_ready_sql', 'bf37bfa6a9d379675709b27eba76f16fe5944dfa',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Максима Горького 151-170', 'Сегодня только консультация, ни чего не просчитывали. Монтаж планируется конец января, начало февраля. О повторной связи договорились 10 января', NULL, 'Зорин',
  NULL, 'буторина', 'связь на 10.01', NULL,
  NULL, '10.01 просил не звонить, примерно через неделю после праздников сам САше наберет, пока не готов, сам позвонит Саше, напомнить Саше. Звонил Саша 10.02 клиент еще думает, попросил набрать позже. Связь 16.02 Запросила у Зорина - 17.02 Саша сказал на чт связь. 26.02 связь на прямую с Сашей. Связь 27.02 в 14:00, Саша должен согласовать время с клиентом, уточнить у него 01.03 мы с Сашей на связи, у него не получилось приехать, кароче инфа по этому адресу у Сани, а он игнорит, поймайте его в офисе. 04.03 звонила от КЦ встречу назначить, сказал, что с Александром только будет разговаривать, передала Саше инфу', 'xlsx_ready_sql', 'e47c0727a85b51567954a379a8740619bbf4d1d7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Русский Вожой Парижская 3', 'пока только консультация, стены не готовы к монтажу, сейчас будут искать маляров. После того как подготовят стены, позвонит в офис, запишутся снова на замер', NULL, 'Зорин',
  NULL, 'ламохина', 'связь через 2 недели уточнимся по готовности стен и предложим перезаписаться', NULL,
  NULL, '27.01 звонила нет ответа, написать не дает Макс, вк профиль закрыт 28.01 нет ответа, написала с нового макса глянуть че каво 04.02 пока ещё не готовы, ремонт в процессе. 04.03 КЦ уточнить на каком этапе ремонт. Будет созваниваться сегодня Саша 04.03 не берет трубку от Саши и кц, попробовать дозвонить. 5.03 по срокам ничего не знает, говорила нервно, сама мол позвонит.', 'xlsx_ready_sql', 'ffa5286ed1e3dd7c5cca1e9483c5ebdc984ad7ee',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Пушкинская 114-37', 'Проконсультировал
После установки кухни наберут', NULL, 'Зорин',
  NULL, 'ламохина', 'уточнить готово ли помещение уже', NULL,
  NULL, 'связь начало Марта, Саша пусть свяжется, она сказала, что с ним на связи. У них еще не готов гарнитур. 02.03 звонил КЦ гарнитур еще не собран. Связь попросила после 15.03', 'xlsx_ready_sql', 'ea859afbde5d87acf2752b32e4285ae51affe998',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Дзержинского 18', 'хотели знать только примерную стоимость, так как монтаж возможно будет летом, пока сами не уверены. Просчитал за наличку со скидкой, предупредил что если будут как юр лицо то будет дороже. Все передадут руководству. В понедельник договорились о повторной связи', NULL, 'Зорин',
  NULL, 'ламохина', 'это детский сад,хотели узнать цену ,по цене все хорошо,будут  копить,потолки будут делать летом поэтому пока не готовы заключать договор,передадут информацию руководству, ставим саше связь на понедельник', NULL,
  NULL, NULL, 'xlsx_ready_sql', '47a602418dd23a2d47bef0e7f98bfb0817ea0de1',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Ленина 4а', 'заказчика нет, встретили строителей. Сказали директор приедет ему озвучат стоимость. Звонить сразу не стали. Повторная связь завтра', NULL, 'Зорин',
  NULL, NULL, 'связь 03.03 с Сашей', NULL,
  NULL, 'руководству передал. Связь через 2 недели', 'xlsx_ready_sql', 'f699cdab010a47e45cf95c0318f4e438a2e26b1c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-14', 'Старое Михайловское Звездная 21', 'Старомихайловское проект. Сегодня скинет. В понедельник связь', NULL, 'Костюнин',
  NULL, 'керенцева', 'в пн уточнить посчитал проект или нет, от этого дальнейший итог, инфа от 02.09 обещали приехать в офис, повторно на конец недели', NULL,
  NULL, 'Старомихайловское через 3 недели поставьте связь мебель еще ждут,06.10 набрали: перезвонит сам, пока еще не до потолков, попробовать набрать через 2 недели. 22.10 сам сказал наберет как готово будет', 'xlsx_ready_sql', '11946ac28979f450872ce69c6949c4eb562db547',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-16', 'Октябрьский 9а-143', 'монтаж в декабре планируют озвучил минималку нужно подумать связь на пятницу', NULL, 'Костюнин',
  NULL, 'соковикова', 'связь на 19.09 По консультации осталась довольна, про все акции рассказал. Думает до пятницы, будет ждать звонка , Связь на декабрь поставте пока денег на предоплату нет делают ремонт все деньги уходят туда', NULL,
  NULL, '01.12 сказала, что актуально будет в апреле', 'xlsx_ready_sql', 'b4ff6a6554c157dd6ad4b694e932f61847624c10',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-18', 'Берша 26-67', 'проконсультировал клиента по потолкам ни чего не считал долгострой. По Кондею нужно мультисплит подобрать завтра нужно варианты ему скинуть', NULL, 'Костюнин',
  NULL, 'черпакова', 'Связались с клиентом, все понравилось, в приоритете ока кондиционеры (потолки на 2 плане), ждет от специалиста варианты мульти-сплит системы и будет думать, связь не сказал когда именно. Позвонили 01.10, сказал набрать 15.10 должен дать ответ по кондеру, потолки пока не актуально', NULL,
  NULL, 'Еще не выбрал как выберет отпишется, связаться 1.10 узнать решение. 15.10-сказал сам сделает все.', 'xlsx_ready_sql', '6f5ab1ed9f357567110eb1cd1019e8a2f95dd598',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-28', 'проспект Калашникова 11/3-115', 'потолки. монтаж не раньше чем через месяц а то и два. Приценивается озвучил со скидкой 10%.', NULL, 'Костюнин',
  NULL, 'петрова', 'Связь через месяц. перенос связи на середину декабря инфа от Феди.', '2026-03-16',
  NULL, '15.12 сказал, что ремонт в процессе, в конце января созвонимся. Звонил КЦ 29.01 пока еще рано до потолков, вопрос актуален, связь март попросил', 'xlsx_ready_sql', '0b809d20880c9d30253aea4cab52ac47ff6bac7e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-02', 'Старый Чультем Сосновая 26', 'связь на понедельник скинем ему варианты кондеев', NULL, 'Костюнин',
  NULL, 'петрова', 'все прошло хорошо, ждет связи с мастером по можелям', '2026-04-01',
  NULL, 'недоступен телефон, написали ватсап, пропосил позвонить в следубщем году,щас ставить не будет', 'xlsx_ready_sql', 'beae3f100839f03fecd0e33ed001ece32815831b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-17', 'Агрыз Заводская 39а-1', 'консультация. потолки натянуты, скоро будет сносить перегородки соответственно будут снимать потолки как возведут новые стены вызовут на замер', NULL, 'Костюнин',
  NULL, 'ламохина', 'сама торопится сделать ремонт,скзала будет с нами сотрудничать связь на следуюбщей неделе', NULL,
  NULL, '27.10 заключение договора', 'xlsx_ready_sql', '6980488d9c47db111d9ce6ee9abc1ef867b303a3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-10', 'Холмогорова 115-169', 'расчитал минималку, повторную связь не может сказать, монтаж декабрь-январь, как будет готов сам наберет', NULL, 'Костюнин',
  NULL, 'Зуева', 'всё хорошо прошло, с мастером всё обсудили', '2025-03-13',
  NULL, 'перенесли на следующий год, идет ремонт, монтаж через 2-3 месяца', 'xlsx_ready_sql', '922ff476df4589a47ecaffd31de4919445e0a815',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-11', 'Воткинск Королева 27-3', 'просчитал дистанционно от 18500 сравнивают еще с другими если что сами перезвонят, орзвучена минималка', NULL, 'Костюнин',
  NULL, 'Зуева', NULL, '2025-11-20',
  NULL, '14.11 не дозвон, ВА нет. 19.11 недозвон, игнор', 'xlsx_ready_sql', 'd44d61a3ee59cc5d2738175dcb2113c6150662d1',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-19', 'Шишкина 20/1-34', 'потолки планирует в следующем году', NULL, 'Костюнин',
  NULL, 'соковикова', 'связь на 20.11', '2026-03-16',
  NULL, '20.11 попросила позвонить в конце января,21.11 просила позвонить через три недели. Звонил КЦ 11.02 сроки сдвигаются, попросила набрать в середине марта', 'xlsx_ready_sql', 'ac369efa600aba0cbea86c188e02ad39f73e8a41',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-20', 'Клубная 13в-250', 'озвучил с небольшой скидкой тендер связь через неделю', NULL, 'Костюнин',
  NULL, 'буторина', 'связь на 27.11', NULL,
  NULL, NULL, 'xlsx_ready_sql', '4535a324a452296731a2ccf732c85fccd0a4e588',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-27', 'Первомайский Благодатная 18', 'ответ сегодня вечером либо завтра в обед высота 4.8 не расчитывали на такую сумму 105000 думали 80000 уложиться', NULL, 'Костюнин',
  NULL, 'соковикова', 'трубку не взяла, написали в вотсапе, связь поставили на 28.11 предварительно . Звонить после 11:00. 28.11 Сказала пока откладывают, после новогодних праздников связаться', '2026-04-17',
  NULL, '12.01 не доступна, макс нет, 13.01 договорились созвониться в конце февраля 28.02пока неактуально, месяца через два звонок контрольный попросили', 'xlsx_ready_sql', '8c4f349f5784b85a1c484cc0fc10166fa5d4e4a6',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-29', 'Старое Михайловское Совхозная 1-2', 'денег нет для предоплаты лпр тоже нет монтаж не торопятся хотела примерно знать цену связь на понедельник', NULL, 'Костюнин',
  NULL, 'петрова', 'встреча прошла хорошо, пока думают. на пн связь подтвердила', NULL,
  NULL, '01.12 сказала, что дороговато и будут искать другие варианты, в декабре пока точно делать не будут, пока  делают счетчик, еще не скоро, документы не в порядке. Еще не сделали счетчики, связь конец марта', 'xlsx_ready_sql', '3f242c801afbecdfdae5ea3729280548dd0b322c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-04', 'Воткинское шоссе 61а-280', 'мониторит прокладка трассы 25040 минималка через неделю связь', NULL, 'Костюнин',
  NULL, 'соковикова', 'связь на 11.12', '2026-03-11',
  NULL, '11.12 занимаются другими делами, позвонить после нг, 13.01 пока не до этого, позвонить через месяца 2', 'xlsx_ready_sql', 'f27996fe1ac4e0f72fba2a62fb63581968eae004',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-05', 'Зеленая 55', 'не определенная женщина я говорит после ночи не чего не понимаю диалога не получилось ответ сегодня или завтра', NULL, 'Костюнин',
  NULL, 'петрова', 'инфа получена утром 6.12', '2026-05-15',
  NULL, '08.12 запросила у Феди, перенесла на после НГ, актуально будет ближе к лету', 'xlsx_ready_sql', '478dc06c42c010770c9adaeda2a64ff013f82996',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-06', 'Ключевой поселок 23а-249', 'лпр на месте нет мониторит пару дней нужно подумать', NULL, 'Костюнин',
  NULL, 'Зуева', 'через 2/3 дня готова дать ответ, замер прошел хорошо', '2025-12-25',
  NULL, '09.12 ждет супруга с командировки, 12.12 Макс-не читает, 17.12 не бере трубку, 22.12 не берет трубку, 25.12 уже сделали у других', 'xlsx_ready_sql', 'b82855849feb62e4977582b56c01dfe797e427e5',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-11', 'Воткинское шоссе 53/1-157', 'ответ завтра нужно все взвесить переговорить с супругом озвучил 2 варианта за наличку и безналичный расчет', NULL, 'Костюнин',
  NULL, 'Зуева', 'не берет трубку, чат макс', NULL,
  NULL, '12.12 Макс-не отвечает, созвонились-в этом году не актуально, нсли что до нг сам напишет, 13.01 делает ремонт, пока не до потолков, позвонит сам. Набрать в конце февраля, уточнить КЦ на каком этапе ремонт. 27.02 не берет трубку, набрать повторно позже', 'xlsx_ready_sql', '845d8089f79db4d28c7cc4b91553af0ad85eb3c6',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-10', 'Красная 118/2-208', 'ответ в Понедельник думает либо гипса либо натяжные', NULL, 'Костюнин',
  NULL, 'ламохина', NULL, NULL,
  NULL, NULL, 'xlsx_ready_sql', '96387360799134c95ae4db27be2534aca1115c2c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-10', 'Льва Толстого 24-89', 'затопили соседи нужна смета для того чтобы сосед все оплатил как оплатит так будем переделывать', NULL, 'Костюнин',
  NULL, 'керенцева', 'хочет утрясти все с виновником. связь просил начало февраля', NULL,
  NULL, '04.02 будет суд скорее всего, решим до пн-вт, вопрос с потолком актуален. Звонил КЦ 10.02 вопрос актуален, но позже, так как идут судебные издержки. Связь просил середину марта', 'xlsx_ready_sql', '95e52a4d2f3dfd37b15ce717af5c839899802057',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-11', 'Новая Чернушка Школьная 15а', 'потолки планирует весной сейчас хотела знать ценник связь на апрель -май', NULL, 'Костюнин',
  NULL, 'петрова', 'инфа получена утром-пишу в мах', '2026-04-01',
  NULL, NULL, 'xlsx_ready_sql', '7272b8f836f72dda9592ed32f2c19596f9ebbe49',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-12', 'Максима Горького 34а-77', 'ни в какую сначала говорит плиточник пусть доделает работу потом только потолки она его уже пол года ждет все завтраками кормит сама наберет как все будет готово', NULL, 'Костюнин',
  NULL, 'Зуева', 'встреча прошла хорошо, проблемы с ремонтником, позвонит как решится ситуация', '2026-03-30',
  NULL, '21.01 пока стройка, позвонить через месяц. 20.02 не берет тел с КЦ написала на авито. Потолки пока не нужны, будут делать ближе к лету скорее всего', 'xlsx_ready_sql', '03043d4a6737de3fac14ff949f86a1a60888b94e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-13', 'Магистральная 4/1', 'счет', NULL, 'Костюнин',
  NULL, 'ламохина', 'счет узнать у феди работаем в итоге или нет', NULL,
  NULL, '21.01 Федя, 26.01 Федя-пропал, мне сказал, что в командировке, перезвонить в среду, передали инфу Феде, ждем ответ писали в Максе. 29.01 заказчик должен  отправить ответ Феде, уточнить 30.01 выслал или нет, 30.01 ждет просчет от конкурентов и напишет Феде,31.01 уточните результат. 31.01 не отвечает Феде, в пн контрольный день узнать итог. 02.02 ждет еще предложения от других. Договорились связаться в вторник 03.02 - Федя должен предоставить скидку, инф от Ксюши, 3.02 инф от Феди ремонт делают и только потом будут рассматривать все предложения. Говорить не может, позвонить КЦ, узнать актуальна ли установка 04.03обои доделаем и потом потолки, наберем мастеру сами', 'xlsx_ready_sql', '554f73047d5c6b46ab7cee0e74ea1b60b2527415',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-17', 'Строителя Николая Шишкина 11/3-120', 'монитор связь через 2 недели', NULL, 'Костюнин',
  NULL, 'ламохина', 'все устроило нужно врем на подумать', '2026-03-11',
  NULL, '31.01 сказал пока отменяется монтаж без сроков, говорил не охотно.', 'xlsx_ready_sql', '2592cfcbc41e106ccc46ce3f4c9f1274a9d74b7f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-28', 'Орловское Мужества 40', 'сегодня вечером с семьей все обсудят завтра до обеда ответ', NULL, 'Костюнин',
  NULL, 'петрова', 'встреча прошла хорошо, пока думают обсуждают, завтра связь подтвердил,', NULL,
  NULL, '29.01 не берет трубки с КЦ и Феди. 30.01 инфа от Феди-заазчик делает вентиляцию, у нас самое выгодное предложение, но пока ему некогда, если до четверга не наберет сам, то набрать КЦ в четверг 05.02 в командировке, вернется в конце недели и решат. Звонили 08.02, трубку не берет от КЦ, попробовать еще раз 09.02 я вам наберу, как буду в городе 16.02 звонил ему пока не до потолков говорит не нужно ему названивать он помнит про нас 04.03 автоответчик', 'xlsx_ready_sql', 'a55aa83054f455aa8b1b5545c49c147abc7f7320',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-04', 'Заречное шоссе 73-8', 'ни в какую монтаж через месяц завтра еще ждет кого то озвучил со скидкой 25000 за наличку', NULL, 'Костюнин',
  NULL, 'Зуева', 'от кц трбуку не берет, ставлю на завтра, предложить выезд рук.', '2026-03-07',
  NULL, '05.02 от выезда отказалась, решили отложить на месяц вопрос 01.03 попросила набрать через недельку', 'xlsx_ready_sql', '274c6c7073c15f650eede4cefbd963957e9fd75f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-08', 'Удмуртская 157-114', 'Удмуртская затопили соседи. Вызвал нас для того что бы выставить счет соседу. Еще ждет мебельщиков чтобы они ему тоже сделали просчет по замене. Как согласуют сумму с соседом наберет либо судится будет', NULL, 'Костюнин',
  NULL, 'буторина', 'все устроило, ждут решения от соседа, назначили повт связь на 22.02', '2026-03-25',
  NULL, '22.02 будет работать с нами но сначала я так поняла будет суд с соседом и потом уже обратится', 'xlsx_ready_sql', '13d71ebd3c1be34ac470aaf95158ef8e011f3ef5',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-08', 'Кунгурцева 21', 'Будет думать на счет потолков, делать или нет. Наличкой 50, если р/сч то 90, от стоимости в шоке.', NULL, 'Костюнин',
  NULL, 'соковикова', 'связь вечер', NULL,
  NULL, '9.02: Будут делать после лицензии потолки, сейчас высота не позволяет сделать натяжные. 20.02 доки пока не подавали на лицензирование, только к лету будет актуально.', 'xlsx_ready_sql', '275499f90caca6d0fee4776fe959e1a19a8296a8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-13', 'Красноармейская 136', 'счет', NULL, 'Костюнин',
  NULL, 'Дубовцева', NULL, NULL,
  NULL, 'не отказываются ждут поступления денег, завтра связь. КЛИЕНТ свяжется сам с Федей!! пока преварительно связь 25.02. 25.02 сразу сброс, пишу в тг Григорий: до следующей недели в отпуске, числа 5,6 напишу. Если начальство потревожит, то раньше.', 'xlsx_ready_sql', '30d303e02133d017c7f09f7ff65a69f061b5ea3b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-14', 'Холмогорова 115-118', 'монтаж и договор планирует через месяц. Озвучил со скидкой за наличку. Мониторит ждет сегодня и завтра гостей', NULL, 'Костюнин',
  NULL, 'керенцева', 'от звонка отказалась, хочет ждать других и понимать цену всех. Когда всех промониторит, будет готова поговорить с руководством и дальнейших скидках. Попросила связаться с ней в четверг 19.02, связь поставила', NULL,
  NULL, '19.02 не готова принять решение. У всех примерно одинаковый ценник, потолки планирует через месяц. Попросила набрать в конце марта, инф от Феди', 'xlsx_ready_sql', '0c5c49cc03ce920851f3628ea4d7ef39eeee3e72',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-18', 'Максима Горького 34а-23', 'сказал встречаться нет смысла отправить проект', NULL, 'Костюнин',
  NULL, 'ламохина', 'просчет проекта', NULL,
  NULL, '19.02 Федя должен посчитать и выслать расчеты. Информация от 19.02 - озвучил стоимость по минималке, по срокам не понятно, клиент сказал перезвонит сам, идет сройка - по связи Федя ориентирует примерно 2 месяца, давайте позвоним через месяц', 'xlsx_ready_sql', '11f75e0a50005834f89c04ebae3eeaaa593269bb',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-03', 'Чехова 46-40', 'еше хочет вызвать 2 конторы монтаж планирует на май. Договорились связаться на следующей неделе в среду. По цене нормально сказал. Озвучил минимум', NULL, 'Костюнин',
  NULL, 'ламохина', 'замеры прошли хорошо,все устроило, ставим частника', NULL,
  NULL, '04.03 Мокрушин: Он частников принципиально не рассматривает, ставлю на среду', 'xlsx_ready_sql', '21e126ef5af7459ad48ffc7d55845bcda6f774ca',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-03', 'Ягульские Просторы Лютиковая 29', 'клиенту надо подумать, Федя свяжется 04.03 в обед', NULL, 'Костюнин',
  NULL, 'Дубовцева', NULL, NULL,
  NULL, 'от Феди: завтра нужно от частника ставить, он сказал мне до завтра думают, поставила частника. 5.03 инфа от Ильи мах: На завтра поставьте , Федя еще должен с ними созвонится . Если не заключится я завтра наберу (от частника)', 'xlsx_ready_sql', '4bb6e75bf2c759894442ac8daecfc9e1ca9e94c9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-04', 'Русский Вожой Миланская 6', 'Европа проект. Как скинет начну считать', NULL, 'Костюнин',
  NULL, 'Зуева', 'нет просчета, ставлю связь на вс', NULL,
  NULL, NULL, 'xlsx_ready_sql', '989b785ea8d11e5788af6e4ab8ae7bce6c1c25ad',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Пироговская 5', 'долгострой , монтаж примерно декабрь и то не точно . Нужна была консультация и примерный ценник . Примерно просчитали озвучил с небольшой скидкой . Связь на ноябрь', NULL, 'Наумов',
  NULL, 'ламохина', NULL, '2026-03-09',
  NULL, '3.11 прока не готов идут ремонтные работы просил о связи в конце декабря , 25.12 не готов принять-еще примерно 3 месяца', 'xlsx_ready_sql', '20549d9adf8e4bcd6e022e3c3c5bbd3d51624814',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Воткинское шоссе 45-123', 'наша шумоизоляция для их решения проблемы не подходит .', NULL, 'Наумов',
  NULL, 'петрова', '-', NULL,
  NULL, NULL, 'xlsx_ready_sql', '38ce4db51fdb09d74df386c89f3cfcedeb8cf60f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Первомайский Полярная 43', 'дом нет крышы, стены на 50% пока идет стройка. была консультация только', NULL, 'Наумов',
  NULL, 'петрова', 'написала в ва 89127681085, СВЯЗЬ на конец Января 31.01', NULL,
  NULL, '29.01 автотв, набрать 05.02 05.02 стройка в процессе, к вам обратимся. 5.03 связь плохая, написала смс с Викули.', 'xlsx_ready_sql', '34eae8e7261d6faecff107a9737c8ff713f7e5e9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Ворошилова 11-68', 'Ворошилова ответ ближе к вечеру . Аванс пока не готовы вносить . Монтаж нужен в декабре', NULL, 'Наумов',
  NULL, 'керенцева', 'работой довольна, связь подтвердила  11.12,оч странный чел просил в конце  февраля набрать', NULL,
  NULL, 'Хотели заключатся 11.12, передумали, будут делать после НГ, не берут, макс нет, 14.01 не берет, 15.01 не берет, перезвонил, в конце января актуально,26.02 не помнит сумму передала Илье. 5.03 монтаж говорит откладывает, Илье это говорил, они напрямую общаются.', 'xlsx_ready_sql', '813ce765b1208efb951b96e6b686efa8ada8d782',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, '9 Января 215-18', 'нужен был примерный ценник . Только начинают ремонт, сносят стены. Что будет по освещению пока не знают .', NULL, 'Наумов',
  NULL, 'петрова', 'нет связи с заказчицей, чатов нет. свяжемся с ней позже', NULL,
  NULL, '06.02 до потолков рано, маляр должен закончить свои работы. Договорились связаться через месяц 06.03', 'xlsx_ready_sql', '7d5a3c4753814ae8491eb356a18c14398a81f2e6',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Союзная 8Б-369', 'долгострой, ничего не считал, объект от Картапова за откат', NULL, 'Наумов',
  NULL, NULL, NULL, NULL,
  NULL, 'установка нужна в конце мая, ближе к июню', 'xlsx_ready_sql', 'b573398a8982a956885620c78a443defa7c8cb55',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Лудорвай Медовая 1', 'пока консультация какие закладные сделать за гипсокартоном , перегородок нет, монтаж примерно через 2-3 месяца .', NULL, 'Наумов',
  NULL, 'ламохина', 'замеры прошли хорошо,потолки пока делать не собираются ближайшие месяца 3 точно,договорились о связи в апреле', NULL,
  NULL, NULL, 'xlsx_ready_sql', '504bae29ef76ad0e1e24df64754ea71435f95b8f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Совхозная 97', 'гараж без отопления . Размеры снял , поехал в офис считать. Пока что нужна цена наша , мониторит', NULL, 'Наумов',
  NULL, 'керенцева', 'связь 27.01', NULL,
  NULL, '27.01 Илья - цену скинул , ждёт еще от других предложения . Озвучил со скидкой. 02.02 не берет трубку, если выйдет на связь,пробуем руководителя/частника 04.02 потолки планирует ближе к лету, ценник высокий, выезд руководителя в целом не против, но ближе к лету', 'xlsx_ready_sql', '1c38405ab1ac03933afa1769b75ae5201ec00ea6',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Автозаводская 13-62', 'долгострой . Монтаж не раньше чем через месяц . Пока нужен был примерный ценник . Озвучил со скидкой 10% за наличку . Договорились через пару недель созвон', NULL, 'Наумов',
  NULL, 'керенцева', 'консультация понравилась, все устроило, связь попросил середину марта, ставим 16.03', NULL,
  NULL, NULL, 'xlsx_ready_sql', '7508e98e1fdd756a45861ec9da0d488da4f932da',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Первомайский Северная 32', 'монтаж планируют в конце марта . Предложил цену зафиксировать , ответ сегодня вечером как с женой обсудит', NULL, 'Наумов',
  NULL, 'ламохина', 'ставим связь с клиентом на вечер', NULL,
  NULL, '16.02 ещё не готовы дать ответ - Илья , пока трубу не берут, ждемс. 18.02 По всем вопросам решает муж, выбирают еще свет. Попросили набрать 28.02  01.03звонила кц пока ремонт идёт, попозже потолки', 'xlsx_ready_sql', 'cb45e62877fac90bbb3edf94d484ff8db657cce4',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Оружейников 3/2-31', 'только купила квартиру . Пока нужна была примерная стоимость , монтаж нужен не скоро . Озвучил со скидкой за нал , вечером посовещается с семьей чтобы зафиксировать цену . Ответ завтра утром', NULL, 'Наумов',
  NULL, 'керенцева', 'все понравилось, акции были услышаны, связь подтвердила на завтра', '2026-04-20',
  NULL, '19.02 еще не решили, потому что монтаж через месяца, два. Связь 20.02 инф от Ильи. 20.02: Пока решили не делать , свяжутся сами через пару месяцев как дойдут до потолков.', 'xlsx_ready_sql', '1ce7559f31b2efb590ef54c9a610e0144f77df46',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Оружейников 3/2-105', 'монтаж через 2 месяца планируют , пока консультация . Связь договорились на середину марта .', NULL, 'Наумов',
  NULL, 'керенцева', 'монтаж нужен через месяц, два, связь подвердил', NULL,
  NULL, NULL, 'xlsx_ready_sql', '3de6d8493485f6edfbe3d66e2a4069d0dbb5d713',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Строителя Николая Шишкина 8/1-36', 'долгострой , монтаж через пару месяцев после того как соберут всю встроенную мебель . Пока консультация и примерный ценник , озвучил со скидкой . Связь договорились на 3 марта', NULL, 'Наумов',
  NULL, 'керенцева', 'все понравилось, нужно время подумать, связь подтвердили', NULL,
  NULL, '04.03 звонила от кц, связь прервалась, потом не дозвон, узнать у Ильи, когда связь лучше поставить. 5.03 инфа от Ильи: Не готова еще дать ответ, договорились на завтра.', 'xlsx_ready_sql', '17aeb8aa554b19c6d7e7cc546b30420e966988c5',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Завьялово Астероидная 34', 'долгострой , монтаж минимум через 3 месяца. Пока консультация и примерный ценник интересовал . Попросил просчитать несколько вариантов , вечером сегодня связь .', NULL, 'Наумов',
  NULL, 'петрова', 'замер прошел хорошо, сказал мастер направит сегодня просчеты. Также договориись на связь через 2 месяца.', NULL,
  NULL, NULL, 'xlsx_ready_sql', '269425e890bb590014e3b57dc574fab22e0de9a3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Чекерил Сергея Красноперова 11', 'долгострой , пока только снял размеры . Будет встреча в офисе по звонку', NULL, 'Наумов',
  NULL, 'зуева', '04.03 недозвон, ставлю связь на конец марта, инф от Ильи', NULL,
  NULL, NULL, 'xlsx_ready_sql', '96ad8bc74e5b75d22ab5fe0f67a6aa449cd1977d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Оружейников 3/2-64', 'вечером с женой будут решать ,  сразу на договор никак .Озвучил со скидкой , по цене устроило , ответ завтра утром', NULL, 'Наумов',
  NULL, 'петрова', 'замер прошел хорошо, связь подтвердил. ставим на завтра', NULL,
  NULL, NULL, 'xlsx_ready_sql', '96f4987836697b0382b6dd4fb074aa9b860e3c9d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-15', 'Красная 118/1-144', 'Красная замер снял
Готовит проект по освещению
В ближайшие дни. Скинет в телеграм
В понедельник позвоню
Но ждёт ещё Эдем они делали им а у друзей мы. Везде качественно очень
Важна будет цена', NULL, 'Парыгин',
  NULL, 'керенцева', '15.08 не отв. ставлю связь на пн', NULL,
  NULL, 'ждет проект-далее инфа от Кости  8.09 " я же сказал как сможем подьехать сам позвоню" клиенту НЕ ЗВОНИМ', 'xlsx_ready_sql', 'b7e40eda10d229124f2bfa2019231602ee08ee0f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-23', 'Пушкинская 259-14', 'Пушкинская ответ завтра. Проверяет знакомого. Озвучил со скидкой 20℅ . Жена в Москве не могут определиться ещё теневой или с лентой. Стены кривые ужас', NULL, 'Парыгин',
  NULL, 'петрова', 'выбирают между двух вариантов, подтвердил связь завтра вечером со специалситом', NULL,
  NULL, 'Вызвал маляра возьмётся нет выравнивать. Как решение будет позвонит. Вообщем с нами. Просто хочет теневой. / 08.09 инф от Кости " я же сказала как оплатят соседи позвоню" клиенту НЕ ЗВОНИМ', 'xlsx_ready_sql', '20c5fe8e8fc9e072e24c227eabe8fe1cc1328d74',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-11', 'проспект Калашникова 23-22', 'снял замер. Скинули мне проект считай смету скинь. Мониторит ищет кто дешевле. Собирает сметы. Сегодня всё посчитаю и скину ему', NULL, 'Парыгин',
  NULL, 'петрова', 'замер прошел хорошо ждет просчет. сказал напрямую общаются. 13.10 уточнить у Кости итог (т.к 12.10 Костя вых)', NULL,
  NULL, 'инф от Кости долгострои каждую неделю звоню. 9 марта уточнить КЦ на каком этапе', 'xlsx_ready_sql', '5ded809f9cc9cfab714d82c5b9c77bf98fdfb6bb',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-14', 'Карла Маркса 308/2-181', 'Шумоизоляция наша не подошла
Будет заказывать которая заштукатурить можно
Потолки консультация
Их делать весной будут', NULL, 'Парыгин',
  NULL, 'соковикова', 'Связь на 26.01, потолки нужны будут после нового года', '2025-06-06',
  NULL, 'дизайн-проект, Косте передала, идет ремонт, потолки летом', 'xlsx_ready_sql', 'f98a8dbb41f78e479aed818fcaf64a0cc739bc6a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-20', 'Восточные Пазелы Тростниковая 35', 'цыгане
Дорого говорит
Вечером связь', NULL, 'Парыгин',
  NULL, 'ламохина', NULL, NULL,
  NULL, NULL, 'xlsx_ready_sql', '5a4841137fddc6c194c83fbf764abcebc7ba120f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-30', 'Завьялово Витуса Беринга 78', 'одбивают бюджет сегодня по свету ещё приедут. Связь в пятницу', NULL, 'Парыгин',
  NULL, 'петрова', 'написала в ва 89120074929', '2026-03-10',
  NULL, 'пока идет ремонт, потом нужны черновые, договорились созвониться после НГ, 13.01 не берут, макс нет, позвонить в феврале 17.02 денег нет, когда делать вообще не знает, если решаться сами позвонят', 'xlsx_ready_sql', 'bf2d622897343557b50720935833c0a0cc149837',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-04', 'Районная 57/2-51', 'в этот раз квартира дочери Та сказала по телефону дорого для меня  Будет мониторить Связь в понедельник', NULL, 'Парыгин',
  NULL, 'петрова', 'замер прошел хорошо, консультация специалсита очень понравилась. пока будут мониторить, частников и компании. по цене дорого для них, возможно монтаж и в след году будет. сказала сама наберет если что', '2026-06-09',
  NULL, '15.12 сказала, что до лета точно не актуально, отложили', 'xlsx_ready_sql', '18077dc38aed54d0793704b692b4ec4bf9594a50',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-13', 'Воткинск Спорта 177', 'снова вызовут на замер когда перегородки возведут
Помещение на комнаты будет делится
В следующем году
Примерно озвучил 34000', NULL, 'Парыгин',
  NULL, 'ламохина', 'не берут трубку', '2026-02-27',
  NULL, '12.01 отложили, пока денег нет, договорились мемяца через полтора созвониться,инф от Кости не заключен', 'xlsx_ready_sql', '2911b8cab435df17ebc7fe54ed9f50b6abbd554a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-28', 'Союзная 11-53', 'от Рустама замер, заказчик напрямую с Рустамом будет решать (Костя писал в личку нам, сказал в общую не будет писать это)', NULL, 'Парыгин',
  NULL, 'петрова', 'не звоню', '2026-03-25',
  NULL, '09.01 запросила у Кости, 10.01 макс, пока думают, 12.01 сказал перезвонить дня через 3-4, хозяйки квартиры пока нет, мама еще не приехала, примерно через неделю сделать звонок попробовать. 28.01 он в больниче пока не до этого, конец февраля связь. 19.02 вообще не актуально телится мычит,очень странный давайте в марте ему позвоним', 'xlsx_ready_sql', '4a8944c84a9ec0fb6f052efed6bb6e3a9f46b3fb',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-09', 'Холмогорова 74-12', 'консультация. Я же говорит сказал покажите фото замерщику пусть позвонит и объяснит. Я у него в том году был. Как ремонт доделает запишется снова. пока разруха', NULL, 'Парыгин',
  NULL, 'петрова', 'консультация прошла хорошо. пока делают ремонт, где то через 2-3 месяца закончит и сам нам позвонит. ранее мы работали уже с ним, наши работы его устраивают, поэтому обратиться к нам.', '2026-03-26',
  NULL, NULL, 'xlsx_ready_sql', '9fd43c48af9ac9ad0ac478f6028931d8efc808a0',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-19', 'Воткинское шоссе 53/1-73', 'В шоссе в среду в офисе заключение договора, После выбора освещения, приедут с мужем', NULL, 'Парыгин',
  NULL, 'буторина', 'связь на 21.01', NULL,
  NULL, '21.01.2026 встреча по освещению. на связь не выходят, 27.01-просил в пятницу перезвонить, пока не готов,30.01 итог созвона денег нет поэтому договор заключать не будет,но клиент вроде настроен быть с нами поэтому связь ставлю на попозже. 11.02 звонил КЦ пока рано, связь просил середину марта', 'xlsx_ready_sql', '49e62ba5215130996ec26611b975ca5b0a899b9e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-20', 'Крестовоздвиженское Мирный 22', 'пока консультация Делать в марте будет', NULL, 'Парыгин',
  NULL, 'буторина', 'связь на 01.03', '2026-05-01',
  NULL, '01.03 делают проводку, потолки летом', 'xlsx_ready_sql', '05d6d2e25337edb4a908ce81fe1473d99cecc772',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-09', 'Орловское Ежевичная 55', 'завтра связь по шумке делать или нет', NULL, 'Парыгин',
  NULL, 'соковикова', '09.02.2026 связь', '2026-03-09',
  NULL, '09.02 звонила не отвечает 16.02 Ежевичная шумка, будут с нами, сначала гипсой выложат всё потом договор,19.02 пока не готовы делают гипсу. ЕЁ не ТРОГАЕМ, она сама свяжется с нами ( ИНФА ОТ КОСТИ), можно у Кости спросить, есть ли инфа', 'xlsx_ready_sql', 'fdb25ec8d10a8e9ac0b06695aece432d61f30e7e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-09', 'Пазелы проезд Лучистый 15', 'в следующую среду заключение договора', NULL, 'Парыгин',
  NULL, 'зуева', '18.02 заключение', NULL,
  NULL, '18.02 Пазелы сегодня заключение которое. Ещё не готово. Через неделю связь повторно. Строители подвисают. 25.02 стояло заключение: заказчик сказал ему не названивать, но он 100% с нами, заканчивает ремонт-инфа от Кости. Позже узнать итог у Кости.', 'xlsx_ready_sql', '1a8311b1d7ecd6ad84055d136a7fe0db89df3fff',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-14', 'Люкшудья 50 лет Победы 5', 'монтаж в мае июне планирует
Дом без отопления
С женой обсудит вечером связь завтра', NULL, 'Парыгин',
  NULL, 'керенцева', 'все устроило, связь попросил в среду, поставила 18.02', NULL,
  NULL, '18.02 не берет трубку,передала Косте 25.02задерживают зп на работе, в марте может получится предоплату внести, передала инфу Косте, чтоб связался по поводу заключени без п/о. Инфа от Кости: заключение 05.03.', 'xlsx_ready_sql', '33763a2b9dfb07cea23059a42c778c3cfa890efb',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-19', 'Пушкинская 273/1-2', 'проект считать
Сегодня посчитаю со светотехниками
И сразу связь
Приедут в офис', NULL, 'Парыгин',
  NULL, 'ламохина', 'проект считает', NULL,
  NULL, 'Посчитал. Клиенту скинули КП . Думает на чем удешевить. Завтра снова связь. 20.02: Изменения от проекта вносить на следующей неделе. 24.02: Пушкинская времени нет чтоб приехать,  времени нет чтоб приехать, там на связи.', 'xlsx_ready_sql', '8b3fe5e262e1682cbabc8c6cc1ba0dfffc4a85a7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-20', 'Карла Маркса 273-56а', 'ломают стены, примерно цену попросили, сами штукатуры мб покрасят. думают', NULL, 'Парыгин',
  NULL, 'петрова', 'все хорошо, пока голые стены, бетон. устанвока не скоро по срокам не сказала.', '2026-03-21',
  NULL, NULL, 'xlsx_ready_sql', 'ea824b1a17148aa2d85465daf5feade6b457c063',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-20', '10 лет Октрября 49-27', 'бабушка решила в комнате ремонт сделать Подбивает бюджет сколько на обои ламинат потолки выйдет, Связь просила через неделю', NULL, 'Парыгин',
  NULL, 'зуева', 'два раза звоню, нет ответа, ставлю связь на пн от кц', NULL,
  NULL, 'Установка нужна в середине марта, позвонить, уточнить готова или нет. Пока идет ремонт', 'xlsx_ready_sql', '1fc646697da926f2cba9b5854a9d1585c71e86b9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-21', 'Холмогорова 109-210', 'через два месяца только зайдёт бригада на ремонт Потолки примерно в июле августе только делать При мне была дизайнер будет делать проект Предложил зафиксировать Не захотела если вырастет цена ничего страшного', NULL, 'Парыгин',
  NULL, 'зуева', 'всё понятно объяснили, но пока электрика ждёт, он на другом объекте, ремонт только только начали, потолки не скоро ещё', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'a4d2b9bcdc9c9a7f87b7d65cda242d588cf95d1a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-28', 'Гончарная 47', 'замер снял проект сфоткал. Времени ждать нету', NULL, 'Парыгин',
  NULL, 'петрова', 'заказчику ничего не считали, не звоню', NULL,
  NULL, '02.03 считает Костя, 03.03 уточнить отправил ли расчеты клиенту - посчитал 198 со скидкой, частник 160, связь четверг. Всё обсудили решили. Два дня на подумать. В пятницу сам звоню', 'xlsx_ready_sql', '26b9af8a24c1b616a8bac778ae69dbf07201ba5b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-26', 'Подшивалово Свободы 83б', 'ничего не считал. должна завтра скинуть проект, будет р/с, монтаж октябрь/ноябрь. Связь 27.08', NULL, 'Мокрушин',
  NULL, 'керенцева', 'заказчику позвонила, сказала все отлично, отправит проект и приедет к нас в офис, как только отправит проект, Сергей посчитает и свяжется. Уточнить у Сергея, скинули преокт или нет. Общение на прямую с Сергеем', NULL,
  NULL, 'Общение на прямую с Сергеем. 27.08: считаю. Сразу говорю с подшивалово будет долгая история, 01.12 тоже самое, 15.01 еще не знают , что по объему, через две недели созвон. Звонил КЦ 29.01 в больнице лежит , связь просила через месяц 28.02 готовы частично на установку, связь поставила Мокрушину на завтра, узнать итог. 1.03: Там все равно нужно согласовывать выезд, без него никак. Время будет позвоню. 4.03 напомнила Сереже он согласовывает сумму', 'xlsx_ready_sql', 'dc76f761f09b611f8de2272adf1838b69de0d037',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-01', 'Агрыз Карла Маркса 105', 'будет когда то автосервис (здание булто заброшено). фасад сдания будут ломать и ставить распашные ворота', NULL, 'Мокрушин',
  NULL, 'петрова', 'самого замера не было .', '2026-06-01',
  NULL, NULL, 'xlsx_ready_sql', '48f471c1697066a05c9a4db18cf940c38b82d3dc',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-17', 'Холмогорова 107-78', 'проект. Как скинет просчитаю', NULL, 'Мокрушин',
  NULL, 'черпакова', 'Уточнить у Сережи 18.09 скинули проект или нет, связь среда, инфа от Сережи', '2025-10-04',
  NULL, 'заключение 02.10', 'xlsx_ready_sql', '67e9c8703102a2be6b1174d04eca529ac5fb837b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-21', 'Воткинское шоссе 53/1-59', 'срочности установки нет. мониторит. сказали свяжутся с нами сами', NULL, 'Мокрушин',
  NULL, 'петрова', 'написали ва 89273486254-нет ответа', NULL,
  NULL, '28.10 нет ответа на звонок написала в ва-сказал актуально, но позже. , 12. 11 трубку не берет, пишем В ВА, 14.11 трубку не берет', 'xlsx_ready_sql', 'afb7ccbd675c1f3aa98d250888e60c141913ef1e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-13', 'Орловское Василия Меркушева 112', 'встреча по рассрочке в офисе завтра, тк на объекте не было инета', NULL, 'Мокрушин',
  NULL, 'петрова', 'не звоню', '2026-03-26',
  NULL, '14.11 должна была быть встреча по рассрочке-отложили, позвонила 18.11-попросил перезвонить через месяц, 18.12 сказал позвонить в середине января, позвонить в конце февраля,26.02 он какой то ватник потолки не знаю когда теперь позвоните в конце марта', 'xlsx_ready_sql', '72d10b5c2abfaea9948587a76be66154d0cd8a24',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-15', 'переулок Мартовский 1а', 'пока не готовы связь через 3 недели', NULL, 'Мокрушин',
  NULL, 'ламохина', 'все хорошо,связь в декабре', NULL,
  NULL, NULL, 'xlsx_ready_sql', '441ffcd396853bf6be4ae321fb7dd0b4a48a3435',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-20', 'Воткинское шоссе 61 офис 4', 'монтаж в след. году', NULL, 'Мокрушин',
  NULL, 'соковикова', 'связь в марте', '2026-04-16',
  NULL, '25.11 ждут расчеты от Сергея, ему информацию передали, должен связаться,4.03 мне вообще не до этого сейчас мы сами свяжемся позже', 'xlsx_ready_sql', 'f7f140e86c81b950b9339a33c055bbe030615a86',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-24', 'Завьялово Метеоритная 36', 'замеры сняли, будет просчет по дизайн проекту связь завтра', NULL, 'Мокрушин',
  NULL, 'петрова', 'просчетов нет у заказчика, не звоню и не пишу', '2026-03-30',
  NULL, 'расчет проекта-инфа от Сергея 25.11, 28.11 сумма озвучена, до монтажа далеко, после НГ, 13.01 сказал, что не получил расчет, работают маляры, монтаж в ближайшее время, 19.01 запрос замерщику, расчет отправлени, клиент в отпуске, трубку пока не берет. Звонил КЦ 29.01 пока очень рано, связь конец марта просил', 'xlsx_ready_sql', '5d7a829a92f3bd4d0a8c8e3653a56bb8d54635be',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-29', 'Борисово Дальняя 23', 'Сережа считать будет дистанционно без выезда, т.к заказчица скидывает план. монтаж планирует январь/феврать, тогда и связь', NULL, 'Мокрушин',
  NULL, 'петрова', 'заказчице не звоню цен пока не знает', NULL,
  NULL, 'расчет до сих пор не получила, актуально будет в марте, передаю Сергею. 5.03 недозвон, набрать позднее', 'xlsx_ready_sql', '181edcf74e77ff9c80b833e63712ae69f9adad78',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-02', 'Карла Маркса 457-52', 'доедут до офиса по свету, там решение, связь 04.12', NULL, 'Мокрушин',
  NULL, 'керенцева', 'связь 04.12', NULL,
  NULL, '04.12 запросила-Сергей сказал, что сами наберут, 09.12 сказала, что отложили месяца на 3,4.03 недоступен. 5.03 сразу не абонент-без гудков.', 'xlsx_ready_sql', 'd6eb3b1151db99952e8150f6e07ee4b7a77e31c7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-07', 'Ягул Воздвиженская 11', NULL, NULL, 'Мокрушин',
  NULL, NULL, 'после НГ', NULL,
  NULL, NULL, 'xlsx_ready_sql', '9d8f36b6169d45fd6be44498ef1251a96172a94f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-22', 'Старое Мартьяново Славянская 2д', 'снял размеры, ничего не считал, проект скинет через 2 дня', NULL, 'Мокрушин',
  NULL, 'керенцева', 'связь 26.12', NULL,
  NULL, '26.12 еще не скинули проект, утчнить после праздников, заказчик проект не скинул, напомнила замерщику, 21.01 не может получить от дизайнера данные, на днях проект скинет. 29.01 снял трубку сказал перезвоню через 15 мин, звонка нет, теперь трубку не берет. Набрать позже, по голосу вроде норм мужи. Звонили, Актуально, НО дизайнер там все затормаживает, связь через 2 недели 16.02 проект ещё в работе, сам вам наберу', 'xlsx_ready_sql', '3efb9d13de7ac9b7d0c062ca188fcc6f81338eed',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-20', '30 лет Победы 18-13', 'замера не было, будут делать новые стены', NULL, 'Мокрушин',
  NULL, 'зуева', 'ставлю связь на март', NULL,
  NULL, '3.03 стены не готовы и не известно когда будут', 'xlsx_ready_sql', '8a70e8416c624529717cdffe109ab23865add2c7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-21', '9 Подлесная 34-67', 'мониторят', NULL, 'Мокрушин',
  NULL, 'зуева', 'написала в макс', '2026-01-24',
  NULL, NULL, 'xlsx_ready_sql', '4ed10eb955777a27ba6b34a79ecaee29992ba4e8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-30', 'Италмас 9-25', 'встреча среда - пятница следующей недели. Сейчас денег на аванс нет', NULL, 'Мокрушин',
  NULL, 'керенцева', 'все устроило, предварительно ставим встречу в офисе на среду', NULL,
  NULL, '04.02 денег нет пока на потолки(инфа от Сережи) 3.03 денег так и нет вообще сами позвоним', 'xlsx_ready_sql', 'a7765ff3a47c9d923208e00816245c40564828e8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-31', 'Школьная 50-65', 'только начинают ремонт. Монтаж через месяца два. Озвучил со скидкой за нал. Менять по установкам обсолютно нечего. Связь в конце следующей недели', NULL, 'Мокрушин',
  NULL, 'петрова', 'все понравилось, монтаж не в ближайший месяц, договорлись на звонок 7.02. пообщались по заключению, что можно позвонить нам либо специалситу напрямую, рассказала про отдел света на удм', '2026-03-25',
  NULL, '07.02 звонил КЦ пока не до потолков. Договорились созвониться в начале марта,4.03 не до потолков нам позвоню позже', 'xlsx_ready_sql', 'd15a2a690ab1a03cca7bd99cc03f898d8d40924d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-04', 'Тарасова 4-126', 'ебанутая женщина, будет проект, связь от Сережи', NULL, 'Мокрушин',
  NULL, 'буторина', 'связь на 05.02', NULL,
  NULL, '06.02 звонил КЦ пока не готова дать ответ, хочет посоветоваться с мужем. Договорились созвониться в воскресенье. Потолки нужны будут в марте, сейчас заказывают мебель, пока не до потолков', 'xlsx_ready_sql', '6ee903ed1fbb855d2dd16b3694a12ce8f1682d43',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-13', 'Ворошилова 3-28', 'думают, дорого, одна стена на 41000 (инфа от Сергея)', NULL, 'Мокрушин',
  NULL, 'Дубовцева', NULL, NULL,
  NULL, 'установку планируют в марте. 5.03 услышала благодар и сразу скинула.', 'xlsx_ready_sql', '4e948fc837c870a2f9bef54d0f7e9b8fbc6f76fd',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-17', 'Дзержинского 11-58', 'деньги будут в марте, скидка не решает. Связь можно поставить на 10 марта', NULL, 'Мокрушин',
  NULL, 'петрова', 'встреча прошла на 5+ встречу подтвердил в начале марта', '2026-03-10',
  NULL, NULL, 'xlsx_ready_sql', 'b4c63b0dbc79f01078b6d8d1cde32dada95128f0',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-01', 'Старое Мартьяново Клубничная 16', 'на адресе родители. Дом детей, те на севере. Родители толком не знают ни чего. Посчитал примерно. Жду звонка от детей', NULL, 'Мокрушин',
  NULL, 'петрова', 'встреча прошла хорошо. Торопилась, говорит обсуждать будет с владельцами. Через день два узнаем итог.', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'f99d9ca12853e5e26046dbb8426775081ba8066a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-03', 'Динамовская 83 (квартиру не указал)', 'не русские, озвучил с максимальной скидкой, вечером связь', NULL, 'Мокрушин',
  NULL, 'керенцева', 'связь с Сережей', NULL,
  NULL, '04.03 сначала перезвоните позже, потом трубку не берет, гасится. Попробуйте КЦ тоже позвоните', 'xlsx_ready_sql', '726f9186a56387da44c21bc68cb6584e73e989ef',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-04', 'Ворошилова 24-37', 'Ворошилова 24 ответ до вечера, договор планировал через 3 недели не раньше', NULL, 'Мокрушин',
  NULL, 'зуева', 'встреча прошла ок, по решению мастеру наберу, квартира на продажу, если до апреля не продам, то буду делать ремонт, св на апрель', '2026-04-16',
  NULL, NULL, 'xlsx_ready_sql', '8779c3a3d2f32a1849fb711b74fafbd880921247',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-19', 'Ближняя Усадьба Дачная 10', 'двери и отопления нет пока. Стены будут готовы к середине ноября. Есть проект скинет', NULL, 'Гребенщиков',
  NULL, 'соковикова', 'Про акции рассказал, клиент отправит проект. Связи будет ждать от специалиста завтра 20.10', NULL,
  NULL, '20 и 21.10 клмент молчит. в доме нет отопления возможно не до потолков ему-Дима напишет нам, как с адреса будет инфа', 'xlsx_ready_sql', 'c7dc2358bc996fda6eb427de77782013de00c594',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-01', 'Новые Гольяны Лермонтова 23', 'замер можно будет сделать в декабре. Связь декабрь', NULL, 'Гребенщиков',
  NULL, 'буторина', 'связь на декабрь', NULL,
  NULL, 'позвонил заказчик 10.12 - сообщил, что пока откладывается, актулаьно будет в феврале 16.02пока всё тормозим, позже вернемся к вопросу 3.03 недоступен', 'xlsx_ready_sql', 'a494a62d0d263e509f6afd4e880f297199deb186',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-17', 'Воткинск Садовникова 1-45', 'посчитал дистанционно сказала что ехать не надо. Там не готово к  замерам и монтаж примерно через 3 месяца. И это ещё не факт. Минималка 65100.
Местный частник за 50000 делает', NULL, 'Гребенщиков',
  NULL, 'Дубовцева', NULL, '2026-03-11',
  NULL, NULL, 'xlsx_ready_sql', '6e1b92ec146c6f937d7fb270c08fb3666a4f1245',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-20', 'Тихие Зори 2 Лесная 1', 'стены будут готовы в апреле. Консультация', NULL, 'Гребенщиков',
  NULL, 'ламохина', 'замеры прошли хорошо, договорились о связи в начале апреля', '2026-04-01',
  NULL, NULL, 'xlsx_ready_sql', '9c29312c5597e989b65c1731807ce3c7902e5d53',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-24', 'Лудорвай Переулок Весенний 1', 'стены не готовы. Лпр нет. Дал телефон офиса как решат наберёт.', NULL, 'Гребенщиков',
  NULL, NULL, 'недоступна, мах нет. связь на след месяц', '2026-04-15',
  NULL, '28.01 вк нет, мах нет, на звонок не ответила-недоступна. сидит на карантине с щенками связь не ранее чем через месяц пока про потолки даже думать не хочет, щенки еще очень маленькие, ремонт пришлось остановить, апрель-май актуально будет', 'xlsx_ready_sql', 'e8386f0a5c96441ae9d3f66046d679a7f3674f44',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-17', 'Ленинградская 118-69', 'замена полотна. Озвучил с максимальной скидкой 12410.  Ждёт сына сама решение не принимает.', NULL, 'Гребенщиков',
  NULL, 'петрова', 'хочет посоветоваться с сыном, обсудить еще раз все установки, он приедет на выходных. в пн/вторник готова будет сказать решение', NULL,
  NULL, '23.02 звонил КЦ пока клеют обои, по связи договорились через неделю, 02.03 несколько раз набирала, линия занята, написала в макс, попробовать еще раз набрать, еще не разобрались с окнами, связаться 10.03', 'xlsx_ready_sql', '56cbe5165602f73ca961376e0863c924bfe3c114',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-25', '10 лет Октября 64/2-', 'моя хорошая знакомая. Сама не решает ничего. Установки по минимуму простые. Озвучил со скидкой . Вечером муж на связь выйдет решим. На меня же поставьте', NULL, 'Гребенщиков',
  NULL, 'петрова', '-', NULL,
  NULL, '25.02 вечер и 26.02 Диме не отвечает она., тк Муж на Украине, хочет посоветоваться.27.02 инф от димы будет работать с нами ждет дату заключения от клиента, 4.03 по звонку Дима сам скажет когда', 'xlsx_ready_sql', '80e350700a59ed8bbbc2ea295c6613d463a1ad97',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-10', '9 Подлесная 11/2-146', 'В субботу поедет выбирать свет, после этого сама позвонит, инфа от Даниила: В офис не ездила, сама наберет', NULL, 'Иванцов',
  NULL, 'соковикова', 'Трубку не берет, написали в вотсапе, предварительно поставим на понедельник связь. Позвонить, уточнить, ездила ли смотреть свет, инфа от Данила не звонить ей', NULL,
  NULL, 'не выходит на связь', 'xlsx_ready_sql', 'bf427f8b0f928d72247adf15af54f6c170fbce16',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-19', 'Агрыз Гагарина 12(а)', 'коммерческое предложение нужно, это аптека.', NULL, 'Иванцов',
  NULL, 'соковикова', 'Будет ждать коммерческое предложение и звонка от специалиста', NULL,
  NULL, 'счет. 15.10 у данила запросила инф-игнорит. мб все таки счет и заключились не нашла инф в базах', 'xlsx_ready_sql', '1e306870480032d977959a483fffdde82705ed6d',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-12', 'Орджоникидзе 21-16', 'к потолкам ничего не готово, будет ждать в следующем году', NULL, 'Иванцов',
  NULL, 'соковикова', 'будет делать потолки в следующем году, летом', '2026-06-15',
  NULL, NULL, 'xlsx_ready_sql', '4d8e51e60ac21f301863a493e8ac334e292d36f5',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-17', '9 Подлесная 11/1-101', 'думала рассрочка у нас без %, денег нет, связь после НГ', NULL, 'Иванцов',
  NULL, 'керенцева', 'все понравилось, потолки точно после НГ уже, связь после январских праздников', '2026-06-04',
  NULL, '12.01 сказала, что до лета точно отложили', 'xlsx_ready_sql', 'f6f6d7f669aa9f649ffcc4822e0418cb076a9dda',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-28', '5 Подлесная 44', 'ждёт коммерческое,хотела чтоб мы в торгах поучаствовали,на тендере.сказал не участвуемПосчитал по минимален кондиционер с установкой 62670', NULL, 'Иванцов',
  NULL, 'буторина', '-', NULL,
  NULL, NULL, 'xlsx_ready_sql', '5aa1f1b494c44d74547b1a3bf366e5df234f6998',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-02', 'Тарасова 1-75', 'проект, нужен повторный выезд после январских праздников', NULL, 'Иванцов',
  NULL, 'буторина', 'ставлю на 10.01', '2026-03-28',
  NULL, '16.01 не удобно, перезвонит сам или перезвонить позже, 19.01 сроки сдвигаются, созвон через две недели,2.02 сроки сдвинулись на конец февраля 28.02ещё ремонт, наберу как к потолкам ближе дело будет', 'xlsx_ready_sql', '26e4341b3df9708bb9e298c6e5f32e88d17a600c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-01', 'Сизево Добрая 1', 'еще ничего не готово, через месяц набрать повторно', NULL, 'Иванцов',
  NULL, 'буторина', 'нужно связаться и повторно на замер записать, уточнить готово ли помещение', '2026-04-03',
  NULL, '01.03 в командировке до апреля', 'xlsx_ready_sql', '3e0563b0b858d3668a3d5d818af4a878a0d3b21e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-06', 'Районная 57/2-226', 'переделка потолка 16кв.хочет парящий,нишу,световые линии.потолок за нал с 5%скидкой 72500+наша лента и блоки 20700(Сергей считал),цена примерная,точная после прихода мебельщика(нужен перезамер).предложил сразу варианты где если что можно удешевить.сказал наберёт после мебельщика,через 1-2недели.', NULL, 'Иванцов',
  NULL, 'керенцева', 'не берет трубку, связь поставили', '2026-05-06',
  NULL, '20.02 не берет от КЦ вк не нашла. Звонили 23.02, трубку не берет, попробовать еще раз набрать КЦ,26.02 не удобно говорить,27.02 не берет трубки скидывает,написала смс,попробуйте еще 28, 28.02 не берут трубку, смс без ответа 01.03 игнор, попросите Данила связаться ,3.03 ближайшие пару месяцев не планируем установку', 'xlsx_ready_sql', '536d53ccd498fe015d91c62d7585ad723d4261db',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-12', 'ФГУП ВГТРК Песочная 13', 'Счет', NULL, 'Иванцов',
  NULL, 'Дубовцева', 'связь пн 16.02', NULL,
  NULL, '16.02 крыша бежит, пока ремонт-инфа от Данила ,3.03 написала данилу. 5.03 инфа от Данила: устанавливали потолки, чтобы сдержать воду с крыши, течет по стенам. Решили менять крышу, процесс долгий, начнут когда снег сойдет, наберем чуть позже им узнать движения.', 'xlsx_ready_sql', 'dae5090e377018388f2a65ea2dc250d3d80b92d1',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-18', 'Школьная 37-3', 'минималка 34990 предложил зафиксировать со скидкрй ответ через неделю', NULL, 'Иванцов',
  NULL, 'ламохина', 'замер пошел хорошо,взяла время подумать', '2026-03-11',
  NULL, 'попробовать руководителя. 25.02: пока идет ремонт, встал точнее. через пару недель навреное будет готов, по ее словам.', 'xlsx_ready_sql', 'abc93ccc3fda775231722c742a378f0f944c93c8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-25', 'Союзная 109-59', 'примерно через неделю будет делать перегородки.смерил примерно,посчитал тоже.от 16900.сколько помещений будет делать пока не знает ,2,3 или 4.считал 2.сказала после 9 марта связь', NULL, 'Иванцов',
  NULL, 'зуева', 'встреча прошла хорошо, всё объяснили, нужно доделать короба, на 9 число связь подтвердили', '2026-03-09',
  NULL, NULL, 'xlsx_ready_sql', 'a96fae431e58c00772c05119512c30af660d460a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-25', 'Красногеройская 30-30', 'заказчик бухой ,о договоре нет смысла разговаривать.Жена его ходит успокаивает.Она сказала сама позвонит.по цене всё устраивает.посчитал по минимуму 23500', NULL, 'Иванцов',
  NULL, 'зуева', 'вопросов нет, всё хорошо объяснили, по цене утсраивает, связь в пт', NULL,
  NULL, '27.02 не берет трубку, написала смс, попробуйте еще раз. 01.03 два раза не берет трубку, дозвонить попробовать и если что частника. 04.03 не доступен. 05.03 на тел не отвечает, написала смс с Викули, проверить ответ, далее частника наверное. Хз возьмет ли с них тел.', 'xlsx_ready_sql', '463a173af072083431351bd2520e5920d349b825',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-26', '50-летия ВЛКСМ 25-6', 'завтра в офисе встреча в 12:00 сегодня некогда .', NULL, 'Иванцов',
  NULL, 'петрова', 'встреча прошла замечательно, все понравилось. встречу на завтра в офисе подтвердил. ставлю 27.02 в 12:00', NULL,
  NULL, '27.02 игнорит Данила и нас, на встречу не приехал, дозвоните 01.03 вопрос пока не актуален и сбросил  трубку, ставлю связь позже', 'xlsx_ready_sql', 'd22ef39cb4236fd62426f486f69a37b1f0cd7e8c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-27', 'Университетская 6/1-10', 'мужа нет,жена ничего не решает,попросила просчитать кучу вариантов,чтоб вечером обсудить с мужем.посчитал 9 варианты от 100000 до 175000.вечером связь.', NULL, 'Иванцов',
  NULL, 'ламохина', 'связь вечером далее по ситуации', NULL,
  NULL, '01.03 ждут деньги, 10 числа сказал связь', 'xlsx_ready_sql', '79c8397cb178437a3400f10b6c037fc0d4a89494',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-03', 'Воткинское шоссе 83/2-141', 'цена устраивает,но заказывать будет когда приедет муж с деньгами,с командировки,после 10апреля.', NULL, 'Иванцов',
  NULL, 'ламохина', 'сбрасывает ставим связь 10.04', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'd5f3806ad63bc76a50d47d6270f2b1b2351194d7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-05', 'Первомайский Северная 21', 'есть предложение от частника за 140000,посчитал с профилями более дешовыми чем считал частник со скидкой 188000,с дорогими профилями,те что частник считал,выходит 241960 со скидкой(эту сумму даже озвучивать не стал).по освещению пока не определились,сказала как решит инфу отправит,чтоб посчитать установку света.', NULL, 'Иванцов',
  NULL, 'петрова', 'все прошло хорошо. ждут еще одного мастера на выходных, будут сравнивать. Договорились на созвон в начале след недели, узнать ее решение окончательное. поставлю частника на завтра', NULL,
  NULL, '6.03 едет частник', 'xlsx_ready_sql', '95ecf23b0e142522fe192de1c55789b6d088e643',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-05', '10 лет Октября 79-92', 'заказчик пьяный, считали теневой, пкшки, световые линии. пальцем в небо установки выбирал, супруги на адресе не было. Есть знакомые частники с втк, возможно будет приглашать их. Завтра заказчик уезжает в глазов (живет там). Нужен повторный выезд.', NULL, 'Иванцов',
  NULL, 'петрова', 'встреча прошла хорошо, взял неделю на подумать. связь на 11.03 согласовали.', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'eedc2a6dbce37b18f1e2c05ef311155af68f3ea9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-08-11', 'Университетская 6/2-64', 'посчитал и трассу для кондиционера и потолки, планирует начать работы черновые сентябрь-октябрь, точный просчет не делали, посчитал примерно, так как будут еще изменения', NULL, 'Ашот',
  NULL, 'ламохина', 'С клиентом связались всем доволен, связь назначили на начало Сентября, не выходит на связь попробовать набрать еще раз 03.09', '2025-09-03',
  NULL, 'звонила 03.09 - Повторно не звонить, сам свяжется. Голова пока забита другим. Сам вам наберу', 'xlsx_ready_sql', '453d17e967c7a76a96ec8f607f6e59bee43324ab',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-22', 'Екатеринбург Старых Большевиков 3-91', 'Старых Большевиков ответ даст после 18 часов', NULL, 'Ашот',
  NULL, 'буторина', 'все супер, очень похвалил работу Ашоту, сегодня-завтра примет решение', NULL,
  NULL, 'инфа от 22.10 последняя компания не приехала, думают до завтра ЭТО ВИПСИЛЛИНГ ПРОБИВАЛИ НАШИ ЦЕНЫ ИНФ ОТ АШОТА', 'xlsx_ready_sql', 'cf557ca92898632163542dc3470c9b4772681450',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-28', 'Семеново Цветочная 1', 'монтаж планируется ближе к весне/лету, сейчас прицениваются, сравнивают, дал хорошие советы, которые до меня не жавал никто, вроде как заинтересован в сотрудничестве, связь на пятницу договорились', NULL, 'Ашот',
  NULL, 'ламохина', 'монтаж хотят только на следующий год, связь в пятницу подтвердил', NULL,
  NULL, '02.02 звонил КЦ, очень рано еще до потолков. По связи договорились на апрель', 'xlsx_ready_sql', 'f85e2ada5c54fe4743faa461d1c8bc34942d795a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-28', 'Кунгурцева 8-67', 'связь в четверг в 20:00, нужно все согласовать с мужем, он в командировке', NULL, 'Ашот',
  NULL, 'ламохина', 'всем довольна ждет мужа потом связь', NULL,
  NULL, 'не отвечает на звонки, написала на ва - пока думают 7.11 написала на ва , созвонились 10.11, ждут несколько предложений, договорились созвониться через полторы недели, 21.11 пока все приостановили, может быть через месяц будут делать. пока не знают, когда будут делать, в январе точн нет, договорились созвониться в начале февраля. Звонили 04.02, не знает когда  хотят установку. Сказали, что свяжемся в марте, уточним по актуальности', 'xlsx_ready_sql', 'febbba6daa001c80d251e55b67c19173eaf821b3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-03', 'Ягул Гвардейская 58', 'ничего не мерили и не считали, заказчик вообще не знает что хочет, связь на 20 числа января', NULL, 'Ашот',
  NULL, 'зуева', NULL, '2025-04-25',
  NULL, 'запрросила у Ашота , 21.01 сказала,что работы приостанвили, по срокам вообще ничего не понятно', 'xlsx_ready_sql', '52cdb8d7ae64534c0ad7036e9e579eb4a708acbf',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-08', 'Новаторов 2/2-3', 'Новаторов посчитал несколько вариантов, пока что для них очень дорого, то что они хотят, по дате связи не мог пока сказать, но с клиентом на связи', NULL, 'Ашот',
  NULL, 'керенцева', 'все понравилось, будет мониторится, если +- цена будет такая же, будут с нами. По связи договорились через неделю, готова будет дать ответ. просила позвонить ей в пн 17.11', '2026-04-10',
  NULL, 'просила позвонить ей в пн 17.11 , пока думает, попросила 09.12 перезвонить, 09.12 попросила ближе к вечеру позвонить, 11.12 написала в Макс-переносится на весну,3.03 давайте отложим еще на месяц', 'xlsx_ready_sql', 'cbf8803c27e952ef90aaf007b4397532a0ba1d59',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-15', 'Новое Завьялово Астероидная 15', 'монитор, монтаж нужен до нг, решает сын, что она хочет по освещению особо не знает', NULL, 'Ашот',
  NULL, 'буторина', 'не отвечает, поставим на среду', '2026-01-29',
  NULL, 'не выходит на связь с Ашотом и мной, 23.01 не берет, звонили 29.01, не берет трубку, на связь не выходит', 'xlsx_ready_sql', '6fa556a431710914ef46d78e9d071c3978144a09',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-24', 'Завьялово мкрн Солнечный Оранжевая 18', 'тепловоз связь после нг', NULL, 'Ашот',
  NULL, 'ламохина', 'время на подумать связь после нг', NULL,
  NULL, '10.01 не взял, написала в макс, в отпуске, напишем через 1.5 недели, 27.01 сброс. 30.01 звонил КЦ - заказчик уехал на отдых, связь попросил через 2 недели. 16.02 говорить не удобно, проси набрать послезавтра. 18.02 недозвон. 25.02 не берет тел, написала на авито.27.02 написала, просит расчеты, инфу Ашоту передали, а вот что дальше уже вопросик. 4.03 расчеты не получил, 5.03 предлагаем связь от руководителя, ждем ответ.', 'xlsx_ready_sql', '3094d51d146b89d07183cdb40b702084dcf6d752',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-18', '7 Никитинская 12-100', 'связь через 3 месяца', NULL, 'Ашот',
  NULL, 'ламохина', 'позвонить в апереле', '2026-04-14',
  NULL, NULL, 'xlsx_ready_sql', '3e2c7188311f49687a1b52773b6d888e806f3869',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-18', 'Истомино 12', 'связь на среду', NULL, 'Ашот',
  NULL, 'соковикова', '21.01.2026 связь', '2026-04-03',
  NULL, 'Хотят обработать от грызунов, потом сделать натяжные потолки, предложила закрепить цену, недельку думают. 28.01 будут делать потолки только в ванной комнате и пока думают, говорить не удобно, предложила мастеру инфу передать для заключения-не готова. Информация 30.01 - Ашот должен отправить расчет только на ванну, о связи договорились на чт 05.02 Ашот не отправил данные по просчету, связаться в пн 11.02 говорит просчет всё ёще не получила, потолки нужны только в ванной, три раза клиенту звоним и одно и тоже ей приходится повторять, итог узнать у Ашота . 16.02 ашоту не отвечает, КЦ звонил сказала что отложила установку просила ей не звонить, сама наберет!', 'xlsx_ready_sql', 'e739b811f1b629dea960e0026ca298e46745decd',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-18', 'Старый Чультем Дачная 10а', 'Усадьба потолки хотят ближе к маю, щас примерный ценник интересовал', NULL, 'Ашот',
  NULL, 'керенцева', 'все устроило, связь апрель-май', '2026-04-27',
  NULL, NULL, 'xlsx_ready_sql', 'b4412c06622c8f240dde5ca1bc5834d57e791f63',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-18', 'Архитектора Сергея Макарова 6/2-205', 'Встреча в офисе 1.02', NULL, 'Ашот',
  NULL, 'зуева', NULL, '2026-02-22',
  NULL, 'связь через 2 недели инф от Ашота 17.02 Ашот на связи с ними, от кц не взяла  22.02 уже установили', 'xlsx_ready_sql', '54ed9cefff66cf2a177bb999ff26fdfefa4bece7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-24', 'Архитектора Сергея Макарова 3/1-138', 'пока нужна только цена, связь 14.02. Монтаж в лучшем случае в марте', NULL, 'Ашот',
  NULL, 'петрова', 'встреча прошла хорошо, связь подтвердил', NULL,
  NULL, 'Звонил КЦ 14.02 пока рано, попросил связаться в конце февраля,27.02 недели 2 еще будет ремонт попросил связаться в конце марта', 'xlsx_ready_sql', '3f0f2b70b7fc64f52857b14c837f96b6e3b438f7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-30', 'Камбарская 94/1-21', 'встреча в офисе по освещению', NULL, 'Ашот',
  NULL, 'ламохина', 'уточните итог 31.01', NULL,
  NULL, '31.01 написала Ашоту-сказал приедут в офис и там решат по договору, завтра узнать. 02.02 звонил КЦ по договору пока рано, ищут определенную люстру, по связи договорились на 16.02. Трубку не взял, попробовать еще раз, 18.02 автоответчик. 25.02: не але. 28.02 не берет трубку 01.03 не берет, ставлю частника. Информация 02.03 решили сначала ремонт доделать, двери поставить, потом потолок. Так как на него пока финансы не позволяют. Но мы говорит 100℅ с вами никого не ищем. Как готовы будут позвонят', 'xlsx_ready_sql', 'deb7bcc1c44f09db0de0be319452c595337eb457',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-03', 'Новаторов 2/1-229', 'посчитал примерно, по установкам еще не определился, делать планирует март-арпрель, связь на начало марта договорились', NULL, 'Ашот',
  NULL, 'керенцева', 'все устроило, связь на начало марта', NULL,
  NULL, '3.03 недозвон,4.03 недозвон,гасится от нас?. 5.03 написал смс на Викулю, что сдвигает монтаж на 1\\2 месяца.', 'xlsx_ready_sql', '2574553aaaf488c00af60e4a7fde6c55e24ab50f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-11', 'Ключевой Поселок 83а-2', 'выставляем счет', NULL, 'Ашот',
  NULL, 'керенцева', 'счет. Набрать вечером КЦ 11.02 уточнить решение', NULL,
  NULL, 'Посчитал около 20 тыс, клиент возмущен, Ашот должен был предложить скидку за нал, расчет предоставил, заказчик говорил, что будет мониторится, он ему за нал скинул просчет со скидкой, тот пока ничего не ответил, он за рулем. Предлагаю позже связаться сегодня с клиентом по обратной связи и тд, уточнить - инф от Ксюши. 12.02 связывается Ашот с ЛПР. УТочнить у Ашота, отправили ли им КП ,15.02 запросила у Ашота инфу он скорее всего не ответит пж уточните у него в пн что по этому адресу . 16.02 монитор, игнорит, пишет ей в тг. 17.02 завтра заключение. 19.02 инф от Ашота заказчик пишет что пока не может принять решение', 'xlsx_ready_sql', 'd129f12015c8da8667ab7d1080d2175c08214148',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-13', 'Ухтомского 26', 'проект, выезжать смысла не было, буду считать по проекту, связь на понедельник', NULL, 'Ашот',
  NULL, 'зуева', 'ставлю связь на пн', '2026-06-01',
  NULL, '14.02 Ашот считает проект, 16.02 узнать тог. 16.02 начал считать, узнать итог. 18.02 инф от Ашта что потолки планируют в Июне', 'xlsx_ready_sql', '5b7fcd1e4429a41d8a78dcf02cfb2142298c8bd7',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-19', 'Архитектора Сергея Макарова 3/2-25', 'тендер, посчитали примерно, монтаж через месяца 2. Связь через 2 недели', NULL, 'Ашот',
  NULL, 'керенцева', 'все устроило, монтаж интересует 1.5-2 месяца, связь подвердила', NULL,
  NULL, '05.03 не берет трубку, попробовать набрать ещё раз и потом перенести св после отпуска Ашота', 'xlsx_ready_sql', '1afa5e93b9c29773c9ab04efd9f337930824c355',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-22', 'Тверская 55а-93', 'связь через 2 недели, посчитал в разных вариантах, никакую', NULL, 'Ашот',
  NULL, 'зуева', 'не берет трубку, автоответчик. Перезвонила, от руководителя отказалась, всё равно говорит у вас буду заказывать, связь через пару недель Ашот', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'f531f415998554d20879ea20a6875d7941dc6fdf',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-27', '40 Лет ВЛКСМ 29', 'примерно померил, все обсудили, связь на 12.03, пока рано о потолках говорить', NULL, 'Ашот',
  NULL, 'соковикова', 'по консультации все отлично, сейчас будут подготавливать стены, связи будет ждать в марте', '2026-03-12',
  NULL, 'пока ремонт доделыаает, потом ждет звонка от Ашота', 'xlsx_ready_sql', '1d1cd9723194958cc0289c51bc70aa3a8fa70155',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-09-06', 'Екатеринбург Первомайский Рощинская 53', 'Нужно просчитать смету, монтаж через 2 месяца. Повторную связь на вторник поставили', NULL, 'Иванов',
  NULL, 'соковикова', 'Сегодня отправил рассчеты, завтра связь 10.09,  Меняются установки. Жду инфу от клиента связь на 15.09,  взял паузу подумать с ндс или без него работать. Жду ответ.', NULL,
  NULL, 'Попросил связаться в конце ноября, монтаж на декабрь планируется. 26.11 не берет трубку, написала на ВА - ответил актуально после НГ. 12.01 написала в МАХ-Дмитрий 89222177707. 16.01 в процессе отделки, говорит не охотно по тел, в мах игнорит. ПОТОЛКИ планирует в МАЕ, уточнить, актуально ли еще', 'xlsx_ready_sql', '617fb3731e233b852d500fcd282f9c8fb234ca41',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-02', 'Екатеринбург Краснолесья 96-201', 'встреча в офисе по освещению 11.01', NULL, 'Иванов',
  NULL, 'петрова', 'Связь в пятницу, инфа от Ильи', NULL,
  NULL, 'С клиентом на связи, пока делают ремонт. Связь по звонку', 'xlsx_ready_sql', 'e49485fcd0c1ec2c5c14c28867a5053fdf825123',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-07', 'Екатеринбург Нагорная 16-193', 'дизайн проект. Пока собирает предложения, будет сравнивать. Связь на понедельник', NULL, 'Иванов',
  NULL, 'петрова', 'пока считают, заказчик цен не знает', NULL,
  NULL, '11.11: отправил коммерческое предложение. С клиентом на связи.', 'xlsx_ready_sql', 'c2af111c6f9601166d90585aa1a8a7a056dc43ad',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-20', 'Екатеринбург Комсомольская 10б', 'Комсомольская счёт', NULL, 'Иванов',
  NULL, 'керенцева', '-', NULL,
  NULL, NULL, 'xlsx_ready_sql', '2eec06b86341db9f53c34c78e3c737ffce259dee',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-20', 'Екатеринбург Солнечная Золотистая 8-541', 'там без договора выезд на перекрой, уже сделали', NULL, 'Иванов',
  NULL, 'керенцева', 'там без договора выезд на перекрой, уже сделали', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'd1de3392e03e67193ce1925244163c23831d466f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-25', 'Екатеринбург Монтерская 8/2-584', 'Клиент не местный, из Нягани, приезжает редко. Отпуск у него с 12.03. тогда и планирует начать ремонт. Попросил связаться 16.03.', NULL, 'Иванов',
  NULL, 'петрова', 'замер прошел хорошо на все вопросы ответили, все понятно. связь подтвердил', NULL,
  NULL, NULL, 'xlsx_ready_sql', '52e89bb198b24aa5f65f4f835e0c149ba89b9932',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-28', 'Екатеринбург Переулок Коллективный 19-103', 'Денег на аванс нет, хотела узнать стоимость. Попросила набрать через неделю, когда появятся финансы.', NULL, 'Иванов',
  NULL, 'петрова', 'написала на авито (поклейка обоев)', NULL,
  NULL, NULL, 'xlsx_ready_sql', '01eb21817fe15cf41060c8e47ade7b1d0f68a5b9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-04', 'Екатеринбург Кузнечная 83-85', 'Кузнечная ответ сегодня в 21.00', NULL, 'Иванов',
  NULL, 'ламохина', 'бронь монтажа 10.03', NULL,
  NULL, '05.03 Будет встреча в офисе по освещению в понедельник', 'xlsx_ready_sql', 'c1d808b695f189576245fdae8832d77c3c27f823',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-10-18', 'Екатеринбург Амундсена 118-176', 'Хотела световые линии, но не укладывается в бюджет. Просчитали другие варианты освещения, подумает. Связь во вторник', NULL, 'Витя',
  NULL, 'петрова', 'не ответила на звонок, написали на авито частник. Все устроило, будет ждать связи от специалиста. Связаться Вите 25.10', NULL,
  NULL, '21.10 написала на авито, проверить, ответила или нет, 22.10 написали авито частник . Информация 25.10 от Вити : Сказала на неделе как удобно будет заедет да подпишет в офисе. 26.10 Витя отправит дог-р в офис заказчица там заключит-проверить статус адреса в 1с', 'xlsx_ready_sql', '0da19a8e9aae06baa83c4d55d40de5eca9b51915',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-11-12', 'Екатеринбург Айвазовского 52-503', 'думают или же всю квартиру делать сразу или по комнатам и перед выбором теневого ПВХ и обычного. Связь в понедельник', NULL, 'Витя',
  NULL, 'зуева', 'всё прошло хорошо, надо подумать', '2025-11-28',
  NULL, 'Айвазовского пока у них нет решения, мониторят ещё. 26.11 написала на ВА - ответила негативно, передала Илье Иванову', 'xlsx_ready_sql', '1a2c79063100cfa9232b618d5eaf08e92b4d1e7e',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Ясная 31-92', 'смотрит ещё варианты, сегодня связь', NULL, 'Витя',
  NULL, 'керенцева', 'связь 20.11, Максим наберет ему 21.11, узнать итог, Переделка будет по установкам и выбирают свет, связь на ПЯТНИЦУ 12.12,24.12 посмотрит смету и примет рещение позвонить 25.12', NULL,
  NULL, '21.11 нет ответа от заказчика. 24.11 сильно занят пока, вечером с Максимом свяжется узнать итог. 25.11 молчит инфа от Максима 1.12 ещё не определился, попросил написать на ва  89058007799. 4.12 назначили повторной замер на 5.12, добавляется комната (сравнивает 3компании с лучшим рейтингом мы одни из них) Позвонить КЦ УТОЧНИТЬ актуально или нет , Замер стоит 05.12. АКТУАЛЬНЫЙ ЗАМЕР ОТ 05.12. 6.12 запросила, сказал вечером связь у них. ОтВити 6.12: Отправил в тг расчеты, в пнд позвоню если не ответит. 8.12 просил Витю позвонить в 21:00. 12.12: Ждем согласования с его дизайнером по расположению линий в пнд связь. 15.12 пока нет ответа 18.12 Вите не отвечает, от кц трубку не берет . 20.12 абонент не абонент МАХ нет. 23.12 запросила у Вити, ответит завтра до обеда 26.12 связь с Витей завтра у него 27.12 клиент ещё не ознакомился с кп. 6.01 Вите не отвечает в тг (клиент просил писать туда) после праздников будет звонить ему-сегодня еще раз написал-игнор. 16.02: поменяли установки. Переваривает, встреча завтра или послезавтра будет. 17.02 было кп. 18.02♥игнор 20.02: Решает с дизайнером чтоб уложиться в бюджет-завтра созвон. 21.02 от Вити нет инфы, набрать с кц 22.02 решение ещё не принято, попросил не звонить, а писать ТГ 25.02 перенесли дату монтажа на 3, решение должен принять до пятницы, край понедельник утро. 02.03 заказчик заболел, связь на 03.03 от Вити.ОН БОЛЕЕТ, просил позвонить 04.03-сбросил. 6.03 сбросил, написала авитоо Ян викторович.', 'xlsx_ready_sql', 'c94fcbc9f8e3257dc338209480408f53b4b060d8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-01', 'Екатеринбург Кобозева 116а-22', 'Обдумывают предложение, связь сегодня после 18:00.
(Возможно хотят услышать предложение випсилига)', NULL, 'Витя',
  NULL, 'соковикова', 'По консультации все понравилось, связь будет ждать вечером от специалиста, связь 01.12 в 21:00', '2026-04-01',
  NULL, 'Отказались, сказали деньги ушли в другие метериалы для ремонта квартиры, будут делать весной', 'xlsx_ready_sql', 'db54462e2df4ad0ea7fdeedbebb458c4612a13c8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2025-12-16', 'Екатеринбург Химмашевская 9-253', 'Обсудит с мужем, сегодня наберу ей еще раз, вчера трубку не брала.', NULL, 'Витя',
  NULL, 'ламохина', '17.12 ни с нас ни с Витине берет', NULL,
  NULL, '19.12 сброила телефон недослушав, написала в МАХ.-сказала до лета на стоп.', 'xlsx_ready_sql', '5e3f038d7664853e3468c504a85330bdd096aee8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-21', 'Екатеринбург Ткачей 19-226', 'Дизайн проект, кп. Связь пнд', NULL, 'Витя',
  NULL, 'соковикова', 'Ставлю на пнд. По консультации все понравилось, связь будет ждать в понедельник', NULL,
  NULL, '26.01 отправил КП, связь 28.01 Кп отправил, еще смотрят. Сказала напишет. Контрольную поставьте на пт. Будет новое КП, отправлю 29.01 КП Витя отправит сегодня вечером 30.01, запросить инфу у него. 31.01 пока ознакамливаются, связь пнд. 2.02: Жду инфу от светотехников. Давай на среду пока поставим, завтра буду им еще напоминать,7.02 инф от Вити Выслал кп по свету, с аналогами. Выбирает связь в среду 11.02 звонила с кц, всё гуд с мастром на связи, Вите сказали, что пока делают мебель, потом потолки 1-2 мес', 'xlsx_ready_sql', '555cf0730aebc8a3151ebbc67258dae691dff605',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-01-22', 'Екатеринбург Викулова 55-5', 'монитор. связь на сб', NULL, 'Витя',
  NULL, 'соковикова', 'по консультации все понравилось, будут думать. Согласовали на субботу 24.01 связь,2.02 не в городе попросила набрать ей в пт', NULL,
  NULL, '24.01 сказала говорить ей не удобно (хотя весь вопрос слышала) сказала позвонить завтра или еще позже. 26.01 звонил КЦ, остались вопросы по расчетам, Вите передали. 28.01 перенос на неделю примерно, но связь в пнд-инфа от Вити..02 игнор,попробуем еще в пн 13.02 все болеют, пока не до ремонта, сама наберет Виктору, ставлю связь на март', 'xlsx_ready_sql', 'f2539c2f851127e040bd0e386093bba584d882a3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-12', 'Екатеринбург Счастливая 4-188', 'Обдумает, связь вечером', NULL, 'Витя',
  NULL, 'зуева', 'в замере номер ремонтника, Виктор на прямую с заказчиком на связи', NULL,
  NULL, '13.02 Витя попросил напомнить ей набрать. 14.02 не выходит на связь - инф от Вити,18.02 игнор. Набрать 24.02 если не ответит, скинуть. Потолки планируют в марте. Звонка будет ждать от Вити 05.03 не берут трубки ни заказчик, ни прораб', 'xlsx_ready_sql', 'ebdbdb97519501c1a6b376145ac5c34654d2aec9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-13', 'Екатеринбург Калинина 59-214', 'Шумка нужна каркасная. Заинтересовался потолками, но нужно обсудить с супругой по установкам и освещению. Договорились созвониться через неделю', NULL, 'Витя',
  NULL, 'зуева', 'связь на 20.02', NULL,
  NULL, '20.02 пока занят установкой каркасной шумки, через 1-2 месяца нужна будет шумка потолка+потолки', 'xlsx_ready_sql', '14bb466b1b6d3f8128cedd90bfa0df7acf564084',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-18', 'Екатеринбург Белинского 86-574', 'Демонтирует сам уголок потолка, если больше его топить не будут, вызовет нас. Пока не решил делать ли новое полотно связь договорились через три недели', NULL, 'Витя',
  NULL, 'ламохина', 'на телефоне антиспам', '2026-03-11',
  NULL, NULL, 'xlsx_ready_sql', '40d17d949ac276934bf6af63b32f254b76c0187a',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-19', 'Екатеринбург Краснознаменная 4 Ильича 9', 'Нужно будет кп с печатью, будет подавать жалобу в УК и запрашивать компенсацию, после того как деньги за ущерб возместят, будем менять полотно', NULL, 'Витя',
  NULL, 'керенцева', 'все устроило, будет ждать денег от УК. Связь начало марта', NULL,
  NULL, '05.03 ответ от ук будет ближе к 15.03', 'xlsx_ready_sql', 'bef48871cc786d74a55992d3674cf5feb205f243',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-19', 'Екатеринбург Нагорная 16-240', 'кп', NULL, 'Витя',
  NULL, 'ламохина', 'кп, узнать 21.01 выставлен ли счет', NULL,
  NULL, '21.02 Витя ещё не отправил КП 22.02 клиент не получил просчет, какое решение говорит вы от меня хотите. 23.02 просчет отправил, в виде кп отправим завтра с утра. Связь можно поставить на среду - инф от Вити. Встреча на объекте 25.02. 25.02: Дату монтажа забронили, некоторые детали уточняем. Дам инфу когда встретимся дог подписать.27.02 Нужно немного изменить установки. Завтра ему итоговый вариант вышлю и договоримся о встрече. 28.02: Да, кп вышлю новое, свяжусь, встречу назначим. 04.03 просит править договор, ведем переговоры. 5.03: новый дог выслали, юристы его смотрят.', 'xlsx_ready_sql', '67fd3f0fc6c35d31f24eee8a486e1af714b8b34c',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-23', 'Верхняя Пышма Сапожникова 3в-232', 'Кп. Связь через пару дней', NULL, 'Витя',
  NULL, 'керенцева', 'кп, уточнить КЦ дальнейшие движения по адресу', NULL,
  NULL, '24.02: Витя должен отправить кп, завтра набрать клииенту узнать получил ли. 25.02: Просчитал. Спецуху заполнил, утром отправим кп. Если будете звонить то после обеда, окей? А лучше я сам бы позвонил после обеда, считали роскошный максимум. 25.02клиент на работе, с кп ознакомится вечером, напомнить Вите 26.02. Кп выслал, на все хотелки не готова. Бюджет обозначила. Завтра отправим новое кп на новых установках. 27.02 у Вити узнать какое решение клиент принял. 27.02 информация от Вити: отдал просчет, делают кп, сегодня вышлем. Уточнить итог 02.03 КЦ Новое кп выслал, знакомится, думает. В бюджет все равно не входим, пока знакомится еще варик ей подсчитаю. Давайте на вторник поставим связь. Еще один пересчет будет. Сделаю уже не по хотелкам а по бюджету. Сегодня отправлю 04.03 Эту недельку взяла на подумать, в пнд итог', 'xlsx_ready_sql', 'a8bc238caa2fdf2560571976e9091cf13adf7241',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-24', 'Екатеринбург Калинина 11-15', 'На Калинина прицениться хотела на замену полотна. Пока делать не будет, ремонтом занимается', NULL, 'Витя',
  NULL, 'соковикова', 'замена полотна, пока не нужна, делает ремонт, учтонить актуально или нет', NULL,
  NULL, NULL, 'xlsx_ready_sql', '3ddd26666927b07c54ecf380d0dcea73e4b4188b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-25', 'Екатеринбург Циолковского 29а-142', 'Монитор. Мы первые. Вечером обсудит с супругом. Связь сегодня после 20.00', NULL, 'Витя',
  NULL, 'соковикова', 'ПОЗВОНИТЬ в 19:00', NULL,
  NULL, 'Позвонить клиенту в 19:00 уточнить, если клиент согласен ставим Вите заключение, ЕСЛИ продолжает мониторить, то ставим связь с рук-ем, вечером не берет, от Вити тоже, написала на авито, см итог. 25.02 от Вити вечер? завтра в течении дня будет от них ответ, писали ему в тг.  даст вечером ответ. Отпишусь тоже по итогу. 26.02 вечер: не отвечают  на звонки, пишет в тг. 27.02 звонил КЦ дозвонилась до нее, еще думает, сказала на связи с Виктором, напишет ему как примет решение, о связи договорились на 04.03, если раньше примет, даст знать 04.03не дозвон, от Вити не берет, дозвонить 05.03 игнор вити и кц, написала на авито, если игнор, попросить Илью как рука набрать . 6.03 не берет от кц, ставлю рука.', 'xlsx_ready_sql', '52c6e54fb20cd224b567a8366319542759d66db3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-26', 'Екатеринбург Блюхера 89-157', 'Запрос на шумоизоляцию более углубленный, нежели наш материал. Наш материал после демонстрации ему тоже понравился и по цене устраивает. Договор заключать пока отказался, т.к с монтажом не торопится и пока не решил что конкретно делать с шумкой и планирует начать эти работы ближе к апрелю. Связь в тг попросил в конце марта', NULL, 'Витя',
  NULL, 'петрова', 'у заказчика стоит антиспам связаться не можем, авито акк удален. связь поставила на конец марта от Вити (через тг напрямую)', NULL,
  NULL, NULL, 'xlsx_ready_sql', 'a3f2ff9c62ea598fff91d3a481e5e831270ed153',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-26', 'Екатеринбург Волгоградская 204-71', 'Запланирован бюджет 30тр на потолок под определенные установки ради которых затевается замена потолка. Под эти установки 30тр не хватает. Немного изменил установки но с учетом хотелок. Вышло 35. Думает. Связь договорились в пнд', NULL, 'Витя',
  NULL, 'ламохина', 'на телефоне автоответчик пишем смс клиенту ставим связь на пн', NULL,
  NULL, '02.03 автоответчик, на смс не отвечает, попробуйте еще раз набрать', 'xlsx_ready_sql', 'd999fe05419e0d1504da56044c0c43d731c20aed',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-26', 'Екатеринбург Менделеева 18', 'Связь на пнд договорились, с супругой решит делать гибкий карниз или без него', NULL, 'Витя',
  NULL, 'ламохина', 'трубку не взял ставим связь на пн', NULL,
  NULL, '02.03 нет ответа 05.03 решение пока не приняли, в пн свяжемся', 'xlsx_ready_sql', 'b2e8ebb1d96a41d0a78246d17dccbcc749233ba8',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-28', 'Екатеринбург Ореховая 14/2-1', 'В след сб встреча в офисе - выбор света. Думают утопить трек или накладной', NULL, 'Витя',
  NULL, 'петрова', 'встреча прошла хорошо, работой мастера полностью довольна. сообщила что на след неделе встреча в офисе запланирована. поставили в программе', NULL,
  NULL, NULL, 'xlsx_ready_sql', '48b2254ce8e66102b5425bb8c4531a00c58f0e89',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-02-28', 'Екатеринбург М. Ананьева 4-122', '. Есть другие предложения но дороже и другой вид шумки, наш вариант сказала кажется самым оптимальным но решение принять сейчас не готова. Договорились созвониться в четверг', NULL, 'Витя',
  NULL, 'петрова', 'встреча прошла хорошо, все рассказали. взяла время подумать, связь подтвердила', NULL,
  NULL, '05.03 Анастасия сказала на сл неделе набрать', 'xlsx_ready_sql', '6c63344081f51f612cb8d46d2fcd5564469e2edb',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, '2026-03-05', 'Екатеринбург КП Бобровые Дачи Черновая 12', 'Есть предложение от частника в два раза дешевле но без выезда на замер. То что просчитали попросил в электронном виде в тг', NULL, 'Витя',
  NULL, 'петрова', 'сумма не устроила, говорит ему насчитали 120 (дистанционный расчет), а мы 300. ставим на завтра частника.', NULL,
  NULL, '5.03: завтра утром Витя должен отправить расчеты заказчику, там узнаем итог, готов с нами работать или нет (как благодар).', 'xlsx_ready_sql', '00ea917b752a54552c8af6eb4487a5c68dde3cef',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Кольцевая 37-94', 'Лпр нет на месте. Созвон завтра, либо вечером напишут сами', NULL, 'Лев',
  NULL, 'керенцева', 'не отв, связь 10.12, связь на 13.01', NULL,
  NULL, 'Лев должен дать инф. 11.12 запросила. 12.12: сказали перезвонят.  15.12 запросили у Льва-скзал завтра созвон. Сказали что переключились на другое. Может через пару месяцев будут делать. Связь март', 'xlsx_ready_sql', '1a64e5812776f995ea5deb3c06967ed30f333680',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Хрустальногорская 93-187', 'ждут расчет', NULL, 'Лев',
  NULL, 'петрова', 'расчета нет, не звоню , просила БОЛЬШЕ НЕ ЗВОНИТЬ а общаться в переписке в тг 24.12 не готова принять решение', NULL,
  NULL, '26.12 не готова пока приянть решение, НЕ ЗВОНИТЕ мне, писать в тг, связь 05.01. 6.01 написала в тг-сказала после праздников будут решать, пока пауза. 13.01 написала в ТГ, посмотреть ответ. 28.01 напомнить о себе чат в ТГ (готова работать с нами, но пока отложенный спрос). 15.01 работают с другими.', 'xlsx_ready_sql', '3555307b7c072f32843038e6da507c8fca4db304',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Малышева 130б-8', 'Хочет посоветоваться с сыном. Сказали что сегодня ответ не дадут.', NULL, 'Лев',
  NULL, 'петрова', 'всё хорошо прошло, но пока не решили, позже свяжется с Львом , 29.12 попросила связаться с ней после нг', NULL,
  NULL, '12.01 сказала делают пока с\\у и стены не до этого пока. сказала номер наш сохранила и набеерт сама. 26.01 звонила пока очень рано, по связи договорились на март', 'xlsx_ready_sql', 'f1a79d8c05e25255cb3f7d5b6426d47505d83128',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'СНТ Уралец Далматовская улица 73 участок 177', 'ждут просчет', NULL, 'Лев',
  NULL, 'петрова', 'просчета нет-не звоню,29.12 не дозвон написала в максе. Пока делают ему перегородки, связь на 26.01 (от Левы инфа)', NULL,
  NULL, '27.12 должен скинуть спецуху. СВЯЗЬ 29.12, инфа от Левы. 6.01 написала Льву не работает он. 7.01 заказчик сказал  в МАХ что перегородки не готовы. 8.01 нет ответа-завтра связь. 9.01 не отвечает КЦ. 26.01 не берет трубку. Попробовать в начале февраля уточнить актуальность, может будет готов к замерам 09.02 нет ответа набрать 12.02вопрос актуален, через неделю-две перегородки заканчиваем и можно на замер 21.02 неудобно разговаривать, как раз перегородки обсуждаем, они почти готовы, записать на замер надо. Звонил КЦ 23.02, договорились на 24.02 выезд по перезамеру, уточнить у Льва итог. 24.02: Принимал бригадир, померили, сравнили со старым замером, поменяли установки. Нужно коммерческое за наличку и оплата по ИП-завтра проверить отправили ли КП набрать заказчику. 26.02кп отправлено, трубку заказчик не берет от кц , Лев 26.02 обещал еще раз позвонить.27.02 Он не посмотрел наше кп обещал до дома доехать и перезвонить. Если не позвонит, я ему завтра звоню.
Не знает во сколько дома будет. 01.03 абонент не в сети , 3.03 меняет установки, сегодня новое кп. Завтра связь.04.03 Общались, толко там не с клиентом на прямую связь, а с прорабом. Ответ пока не дал. нужно кп на ндс 05.03 Илья отправил кп, звоню клиенту-говорит не видел кп ещё, куда отправили? запросила инфу у Ильи. 6.03: у заказчика не открывается кп, ему отправили фото кп, Иванов в течении дня наберет ему.', 'xlsx_ready_sql', '1455c416ab4bb1d2f0c4e9def524c9082cb35ca9',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург КП Благодатный 2', 'Кп благодарный 2 
Много нюансов. Ждет просчет, связь завтра', NULL, 'Лев',
  NULL, 'керенцева', 'по информации на 14.01 так и не получил просчет напомнила Льву', '2026-01-30',
  NULL, '23.01 Игнор от Льва. 26.01 не берет трубку с КЦ и от Льва, связь через пару дней. НЕ берет трубку. Мы вообще его уже отмели вроде.', 'xlsx_ready_sql', '8c8a784533cee9259c63e3a9f92281a178e37567',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Академика Сахарова 37-163', 'Уже делали потолки у других. Будут делать просчет еще у них. Нас позвали из-за акции. 
Созвон в среду', NULL, 'Лев',
  NULL, 'ламохина', 'замеры прошли хорошо,хотят сравнить цены,связь на среду поставили', NULL,
  NULL, 'ни мониторили похоже, я им скидку сделал хорошую чтоб на ближайшие дни записать их. Сказали что не могут, у них нет денег сейчас. 26.01 звонил КЦ пока не актуально, по связи договорились на конец февраля 28.02пока отложили вопрос, примерно до мая', 'xlsx_ready_sql', 'e63a08566b804d171704329f82f20fc7cf4af477',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Родонитовая 12-61', 'ЛПР нет на месте. Созвон и просчет завтра.', NULL, 'Лев',
  NULL, 'петрова', 'просчета нет не звоню,21.01 Лев еще просчитывает попросил поставить связь 23.01', NULL,
  NULL, 'Родонитовая отправил просчет, думает (у них ремонт еще). 26.01 звонил КЦ - пока не готовы дать ответ, по связи договорились на 02.02. Предложили со скидкой,заинтересовало их. Но пока заключаться не будут. У них тоже квартира строится. Предварительно ставим на 12.02, учнить у Льва 3.02 инф Передумали пока делать потолки, потому что хотят доделать сан узел и ванную. Пока нет денег. Просят созвон через месяц, не раньше.3.03 Не готов ремонт. Связь через неделю', 'xlsx_ready_sql', '21790324b9f3ab462e2a416d48d0c1d6691be7de',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Хрустальногорская 75-16', 'Заключение 30. Делают ремонт пока', NULL, 'Лев',
  NULL, 'керенцева', 'связь 30.01', NULL,
  NULL, 'Они отказались, сказали что им госуслуги взломали и они все приостановили. Через месяц можно набрать. Они в марте вообще хотели монтаж. ИНФА от Левы. Ставим в марте связь  говорит разберется сама и свяжется тоже сама, попробуем набрать в апреле может', 'xlsx_ready_sql', '5fa2b70c960084362cb13fb09a596e40120d9229',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Родонитовая 12-61', 'СОЗВОН в пн 02.02', NULL, 'Лев',
  NULL, 'соковикова', 'инфа от Левы,2.02 готова заключаться должен сввязться лев договориться уточним у него', '2026-03-12',
  NULL, '03.02 - передумали пока делать потолки, потому что хотят доделать сан узел и ванную. Пока нет денег. Просят созвон через месяц, не раньше.4.03 ремонт не готов связь через неделю', 'xlsx_ready_sql', '71a8b3b874e210728a37eca1a2225b4aa7d645cc',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Олега Кошевого 1-43', 'Все понравилось, хотят обдумать варианты где делать шумоизоляцию
Созвон послезавтра', NULL, 'Лев',
  NULL, 'ламохина', 'консультацией доволен, взял время на подумать,связь послезавтра подтвердил', NULL,
  NULL, 'созвон со львом 05.02 в 20:00 ,14.02 У него в семе что случилось и деньги ушли туда. 
Дату созвона он ни в какую не назначил, сказал сам позвонит, возможно шумоизоляцию только летом будет делать. 14.02 попробуем набрать через месяц, думаю, раньше не нужно, раз у человека что-то в семье', 'xlsx_ready_sql', 'e287dec4f97b0306c0705c1f782a35c1ddd55e7f',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, '2026-03-04 00:00:00', 'Лпр нет на месте. Созвон завтра.', NULL, 'Лев',
  NULL, 'зуева', 'связь 06.02', NULL,
  NULL, 'Звонили 09.02, трубки от нас не взял, узнать у льва, какой итог 10.02от кц не берет, какой-то сложный  проект 11.02 Лев считаетю 16.02: отправили кп. Связь завтра от Льва. 17.02 просчитал, КП сделают и скину.  поставь на завтра связь, там со спецухой какая то проблема. Сделаем. 19.02 конкуренция с випсилингом, будем бодаться, связь 23.02 инф от Льва. 19.02: он в отпуске и не может говорить, переписываемся только. Отвечает редко. 
Давай его тоже на четверг следующий поставим.Звонили 24.02, трубку не взял от КЦ, попробовать еще раз,26.02 Випсилинг предложил как мы по цене. Потом они скинули цену, он вообще не хотел после этого общаться с нами. Уговорил его на созвон. Вроде расположил его к себе. Договорились, что он пока не заключается. Посмотрим где мы можем адекватно уменьшить цену будем забирать его себе. 04.03 Лев сам свяжетсмя, напомните ем пж', 'xlsx_ready_sql', 'bcecd7d898498662961a4a4fdb059760208cef66',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Московская 66-64', 'Шумоизоляция стены надо работу в комплексе, будет думать. Связь завтра', NULL, 'Лев',
  NULL, 'соковикова', 'еще не определился по потолкам, думает', NULL,
  NULL, '6.02 клиент не помнит сумм,Лев должен связаться 11.02 Лев считает  12.02 соседи будут делать со своей стороны, посмотрят как будет и если что продолжим работу .27.02 от Льва Она шумку хотела под ключ с отделкой, ее в итоге делают соседи. хочет посмотреть результат, затем принимать решение, поэтому приостановила поиски. Сейчас не знает, они сделали шумоизоляцию или уехали куда то. Поэтому еще ждет.', 'xlsx_ready_sql', '00f88ac2a70e2e08785ca0bdf4f0bb23e2d55d1b',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Заводская 47/1-63', 'Все понравилось, ждет мужа с соревнований, он принимает решение. Она готова работать с нами.', NULL, 'Лев',
  NULL, 'ламохина', 'ставим связь на пн', NULL,
  NULL, '16.02 говрит что просиа Льва все расписал, напрямую общались они. пишу Льву напомнить. 16.02:  скидку предложил и пересчитал завтра связь. 17.02 не берет с Льва, сказал кц не звонить, мол Илья наберет. 19.02 ни с Ильи, ни с кц не берет трубку, 20.02 еще раз попробуйте, если на связь так и не выходит, скинуть. 20.02: Работает с нами. Заключение в офисе 27.02 11-12 инфа от Иванова. ,27.02 вызвали на работу должна заключиться в марте. 28.02 заказчица на работе, сегодня должны по дате договориться вроде как-Иванов: сегодня не готова дать ответ. связаться через неделю ставлю на пт 06.03 Заключение в офисе 12.03 11-12', 'xlsx_ready_sql', 'e33cdc0acb73728e1206de06847b4c81b9a176c3',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Большакова 21-199', 'Большакова ЛПР нет на месте, встречал бригадир до 14:00 будет занята
Созвон болиже к трем', NULL, 'Лев',
  NULL, 'керенцева', 'связь 14.02 в 15.00', NULL,
  NULL, '14.02 узвучил ценник, Связь в понедельник, нужно отправить кп - 16.02 узнать выслал ли КП. 16.02 лев игнорит, добить. 17.02: свет должен сделать. Завтра утром отправлю. 19.02 Позвонил, просила в телеге писать. Просчеты все у нее. Квартира у нее еще на стадии демонтажа. Связь 26.02 - инф от Льва, Большакова хочет все перегородки сделать сперва. Потом потолки 05.03 пока ремонт, потолки примерно через месяц', 'xlsx_ready_sql', 'adad382b5f15be499653566f8a23cb39165373ac',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Менделеева 6-32', 'Посчитал с небольшой скидкой. Хочет посоветоваться с мужем. Думали вообще самостоятельно натянуть, не могут найти кто продает полотно. Созвон во вторник.', NULL, 'Лев',
  NULL, 'петрова', 'замер прошел хорошо, все устроило, связь с мастером подтвердили', NULL,
  NULL, '17.02 откладывает установку потолков до лета, сейчас начинает делать ремонт. связь ближе к лету подтвердила.', 'xlsx_ready_sql', 'db0a9c6369662145238ed2156dadf96d94749cab',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

INSERT INTO non_closures (
  account_id, entry_date, address, reason, measurer_user_id, measurer_name,
  responsible_user_id, responsible_name, comment, follow_up_date,
  result_status, special_calculation, source, unique_hash,
  created_by_user_id, updated_by_user_id, created_at, updated_at
) VALUES (
  1, NULL, 'Екатеринбург Бакинских Коммисаров 33а/5-437', 'Все обсудили померили. Просили скинуть просчет, торопятся. Сегодня отправлю', NULL, 'Лев',
  NULL, 'петрова', 'мы к нему на замер опоздали более чем на час без предупреждения, был недоволен-2 раза нам звонил. звонить не буду сегодня чтобы не нагреть. завтра связь узнать получил ли просчет и узнать решение', NULL,
  NULL, 'Узнать у Левы, клиенты ему должны отписать вечером 18.02. Звонил КЦ 19.02, пока не готовы принять решение, договорились связаться через неделю, 27.02 должна сказать итог,27.02 ответ дать не может тк все решает муж ждет его,сказала сама напишет,инф от Льва ставим 6.03 связь. 6.03: откладывают монтаж тк нет денег, сразу сказала что дело не в нашем предложении, копят, через мес догвоорились созвон.', 'xlsx_ready_sql', 'bbf316c6693bd3c9875625e99a91a142e0c29802',
  1, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
  address = VALUES(address),
  reason = VALUES(reason),
  measurer_name = VALUES(measurer_name),
  responsible_name = VALUES(responsible_name),
  comment = VALUES(comment),
  follow_up_date = VALUES(follow_up_date),
  special_calculation = VALUES(special_calculation),
  updated_by_user_id = VALUES(updated_by_user_id),
  updated_at = NOW();

COMMIT;