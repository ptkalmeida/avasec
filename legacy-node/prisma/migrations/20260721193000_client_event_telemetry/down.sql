-- ROLLBACK: tabela aditiva de telemetria; remoção não afeta dados de auditoria (SecurityLog).
DROP TABLE `ClientEvent`;
