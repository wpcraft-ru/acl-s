# ACL for WordPress by CasePress Studio
==========
## Changelog 
14.07.2014
* При удалении пользователя пользователь удаляется из всех групп в которых состоит
* При удалении пользователя удаляются все меты у постов где он участвует
* При добавлении поста, автор сразу добавляется в список тех у кого есть доступ
* Добавил пользователям возможность просматривать посты разных статусов в админке
* Хранение данных о доступах в отдельной таблице (данные доступов также дублируются в мете постов).
### Описание основных функции для работы с таблицей ACL :
1. ACL_get_post_for_where($subject_id, $subject_type) - функция для выборки постов из таблицы по ИД пользователя, либо по ИД группы возвращает массив ИД постов
2. update_ACL_meta($subject_type, $object_type, $subject_id, $object_id) - функция обновляет таблицу
3. get_ACL_meta($subject_type, $object_type, $object_id) - функция возвращает массив ИД постов
4. delete_ACL_meta($subject_type, $object_type, $subject_id, $object_id) - функция удаляет запись из таблицы
5. check_ACL_meta($subject_type, $object_type, $subject_id, $object_id) - функция проверяет наличие записи в таблице

## Todo
1. Хранение данных о выданном доступе в отдельной мете
2. Хранение данных о фактическом доступе в другой мете
3. Хук для перехвата смены доступа. Чтобы обновлять фактический доступ из множества источников.

