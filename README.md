# acl-by-cps
==========

ACL for WordPress by CasePress Studio

# Todo
1. Хранение данных о выданном доступе в отдельной мете
2. Хранение данных о фактическом доступе в другой мете
3. Хук для перехвата смены доступа. Чтобы обновлять фактический доступ из множества источников.
4. Хранение данных о доступах в отдельной таблице, функции для работы с таблицей ACL
* ACL_get_post_for_where($subject_id, $subject_type) - функция для выборки постов из таблицы по ИД пользователя, либо по ИД группы возвращает массив ИД постов
* update_ACL_meta($subject_type, $object_type, $subject_id, $object_id)
* get_ACL_meta($subject_type, $object_type, $object_id)
* delete_ACL_meta($subject_type, $object_type, $subject_id, $object_id)
* check_ACL_meta($subject_type, $object_type, $subject_id, $object_id)
