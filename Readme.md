## VirtueMart payment module

### Installation

* Backup your webstore and database
* Download [begateway.zip](https://github.com/BeGateway/virtuemart-payment-module/raw/master/begateway.zip)
* Start up the administrative panel for Joomla (www.yourshop.com/administrator)
* Choose _Extensions_->_Extension Manager_
* Upload and install the payment module archive via **Upload Package File**.
* Choose _Extensions_->_Plugin Manager_ and find the Begateway plugin and click it.
*	Make sure that its status is set to _Enabled_ and press _Save & Close_.
*	Open _Components_->_VirtueMart_ and select the _Payment methods_.
* Press _New_.
*	Configure it
  * set _Logotype_ of the payment method. You can use either pre-loaded
    one with VISA and MasterCard logotypes or any own one which you have
already uploaded via _Media Manager_ to the _images/stories/virtuemart/payment_ folder.
  * set _Payment Name_ to _Credit or debit card_
  * set _Sef Alias_ to _begateway_
  * set _Payment Description_ to _VISA_, _MasterCard_. You are free to
    put all payment card supported by your acquiring payment agreement.
  * set _Published_ to _Yes_
  * set _Payment Method_ to _Begateway_
  * click _Save & Close_
*	Open the beGateway payment method and go to _Configuration_. Here you fill in
  * Payment gateway URL, e.g. _demo-gateway.begateway.com_
  * Payment page URL:, e.g. _checkout.begateway.com_
  * Transaction type: _authorization_ or _payment_
  * Shop Id, e.g. _361_
  * Shop secret key, e.g. _b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d_
  * click _Save & Close_
* Now the module is configured.

### Notes

Tested and developed with VirtueMart 3

If you setup the module with default values, you can use the test data
to make a test payment:

* card number __4200000000000000__
* card name __John Doe__
* card expiry month __01__ to get a success payment
* card expiry month __10__ to get a failed payment
* CVC __123__

### Contributing

Issue pull requests or send feature requests.


## Платежный модуль VirtueMart

### Установка

* Сделайте резевную копию вашего магазина и базы данных
* Скачайте модуль [begateway.zip](https://github.com/BeGateway/virtuemart-payment-module/raw/master/begateway.zip)
* Зайдите в панель администратора Joomla (www.yourshop.com/administrator)
* Выберите _Расширения_->_Менеджер Расширений_
* Загрузите и установите платежный модуль через **Загрузить файл пакета**.
* Выберите _Расширения_->_Менеджер плагинов_, найдите Begateway плагин и кликните на нем.
*	Убедитесь, что его _Состояние_ установленов в _Включено_ и нажмите _Сохранить и закрыть_.
*	Откройте _Компоненты_->_VirtueMart_ и выберите _Способы оплаты_.
* Нажмите _Создать_.
*	Настройте модуль
  * в _Логотип_ выберите логотип этого способа оплаты. Вы можете
    использовать либо уже загруженный __visa_mastercard_logos.png__ с логотипами VISA и MasterCard, либо использовать свой, который предварительно был загружен через _Медия-менеджер_ в каталог _images/stories/virtuemart/payment_.
  * в _Название платежа_ введите _Банковская карта_
  * в _Псевдоним_ введите _begateway_
  * в _Описание платежа_ введите _VISA, MasterCard_
  * в _Опубликовано_ выберите _Да_
  * в _Способ оплаты_ выберите _Begateway_
  * нажмите _Сохранить и закрыть_
*	Откройте способ оплаты _begateway_ и нажмите закладку _Конфигурация_. Здесь необходимо заполнить
  * Адрес платежного шлюза, например, _demo-gateway.begateway.com_
  * Адрес страницы оплаты:, например, _checkout.begateway.com_
  * Тип транзакции: _authorization_ or _payment_
  * ID магазина, например, _361_
  * Ключ магазинa, например, _b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d_
  * нажмите _Сохранить и закрыть_
* Модуль оплаты настроен.

### Примечания

Протестировано и разработано для VirtueMart 3

Если вы настроили модуль со значениями из примеров, то вы можете уже
осуществить тестовый платеж в вашем магазине. Используйте следующие
данные тестовой карты:

* номер карты __4200000000000000__
* имя на карте __John Doe__
* месяц срока действия карты __01__, чтобы получить успешный платеж
* месяц срока действия карты __10__, чтобы получить неуспешный платеж
* CVC __123__
