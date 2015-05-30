<?php namespace pulyavin\wmxml;

class Constants {
    // типы транзакций:
    const TRANSAC_IN = "in"; // входящая транзакция
    const TRANSAC_OUT = "out"; // исходящая транзакция

    // типы переводов
    const OPERTYPE_CLOSE = 0; // обычный (или с протекцией, завершенный успешно)
    const OPERTYPE_PROTECTION = 4; // с протекцией (не завершена)
    const OPERTYPE_BACK = 12; // с протекцией (вернулась)

    // состояния счетов
    const STATE_NOPAY = 0; // не оплачен
    const STATE_PROTECT = 1; // оплачен по протекции
    const STATE_PAID = 2; // оплачен окончательно
    const STATE_DENIED = 3; // отказан

    // типы контракторв в арбитраже
    const CONTRACT_PUBLIC = 1;
    const CONTRACT_PRIVATE = 2;
}