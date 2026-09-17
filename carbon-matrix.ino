 #include <WiFi.h>
#include "esp_wpa2.h"
#include <ESPmDNS.h>
#include <WiFiUdp.h>
#include <ArduinoOTA.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Adafruit_NeoPixel.h>

// --- НАСТРОЙКИ ЗА КОРПОРАТИВНАТА МРЕЖА ---
const char* ssid = "OB-CorpNet";
const char* EAP_IDENTITY = "BGBLEQEN";      // Потребителско име
const char* EAP_PASSWORD = "Eqenpass01";    // Парола

// --- НАСТРОЙКИ ЗА LED ЛЕНТАТА (ONE-WIRE) ---
#define LED_PIN    48   // Пинът, на който е закачена веригата
#define NUM_LEDS   16   // Общ брой светодиоди/клетки (16 броя)

Adafruit_NeoPixel strip(NUM_LEDS, LED_PIN, NEO_GRB + NEO_KHZ800);

// --- НАСТРОЙКИ ЗА СЪРВЪРА ---
const char* serverUrlGet = "http://10.171.13.44/carbon_matrix/api.php?action=get_commands&token=my_super_secret_key_123";
const char* serverUrlConfirm = "http://10.171.13.44/carbon_matrix/api.php?action=confirm_command&token=my_super_secret_key_123";

unsigned long lastTime = 0;
unsigned long interval = 2000; // Проверка за нови команди на всеки 2 секунди

void setup() {
  Serial.begin(115200);
  delay(10);
  Serial.println("\nСтартиране на ESP32-S3 и инициализиране на LED лентата...");

  strip.begin();
  strip.show(); // Изчистване / гасене на всички светодиоди в началото
  strip.setBrightness(50); // Яркост от 0 до 255

  WiFi.disconnect(true);
  WiFi.mode(WIFI_STA);
  
  WiFi.begin(ssid, WPA2_AUTH_PEAP, EAP_IDENTITY, EAP_IDENTITY, EAP_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.print(".");
    attempts++;
    if (attempts > 35) {
      Serial.println("\nГрешка при връзката с Wi-Fi! Рестартиране...");
      ESP.restart();
    }
  }

  Serial.println("\nУспешно свързан с OB-CorpNet!");
  Serial.print("IP адрес: ");
  Serial.println(WiFi.localIP());

  // Настройка на ArduinoOTA
  ArduinoOTA.setHostname("carbon-esp32");

  ArduinoOTA.onStart([]() {
    Serial.println("За започване на OTA актуализация...");
  });
  ArduinoOTA.onEnd([]() {
    Serial.println("\nКрай на OTA актуализацията!");
  });
  ArduinoOTA.onError([](ota_error_t error) {
    Serial.printf("Грешка[%u]\n", error);
  });

  ArduinoOTA.begin();
  Serial.println("Системата е готова. Wi-Fi и OTA са активни!");
}

void loop() {
  ArduinoOTA.handle();

  if (millis() - lastTime >= interval) {
    lastTime = millis();
    
    if (WiFi.status() == WL_CONNECTED) {
      checkAndExecuteCommands();
    } else {
      Serial.println("Wi-Fi връзката е прекъсната!");
    }
  }
}

// Функция за преобразуване на s1r1-s1r8 (0-7) и s2r1-s2r8 (8-15) в точен индекс
int parseLedIndex(JsonVariant val) {
  if (val.is<int>()) {
    int v = val.as<int>();
    if (v >= 1 && v <= 16) return v - 1; // Директен номер от 1 до 16
    return v;
  } else if (val.is<const char*>() || val.is<String>()) {
    String s = val.as<String>();
    s.toLowerCase();
    
    if (s.startsWith("s1r")) {
      int r = s.substring(3).toInt();
      if (r >= 1 && r <= 8) return r - 1; // s1r1-s1r8 -> индекс 0 до 7
    } else if (s.startsWith("s2r")) {
      int r = s.substring(3).toInt();
      if (r >= 1 && r <= 8) return 8 + (r - 1); // s2r1-s2r8 -> индекс 8 до 15
    }
  }
  return -1;
}

void checkAndExecuteCommands() {
  HTTPClient http;
  http.begin(serverUrlGet);
  int httpResponseCode = http.GET();

  if (httpResponseCode > 0) {
    String payload = http.getString();
    
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      bool hasCommand = doc["has_command"];
      if (hasCommand) {
        int cmdId = doc["id"];
        JsonVariant pinField = doc["pin"]; // Може да получи "s1r1", цифра и т.н.
        String cmd = doc["cmd"];

        int ledIndex = parseLedIndex(pinField);

        Serial.printf("Получена команда -> ID: %d | Изчислен LED Индекс: %d | Действие: %s\n", cmdId, ledIndex, cmd.c_str());

        if (ledIndex >= 0 && ledIndex < NUM_LEDS) {
          if (cmd == "ON") {
            strip.setPixelColor(ledIndex, strip.Color(0, 255, 0)); // Зелено
          } else if (cmd == "OFF") {
            strip.setPixelColor(ledIndex, strip.Color(0, 0, 0)); // Изключено
          }
          strip.show();
        }

        confirmCommand(cmdId);
      }
    } else {
      Serial.println("Грешка при парсиране на JSON отговора!");
    }
  } else {
    Serial.print("HTTP грешка при заявката: ");
    Serial.println(httpResponseCode);
  }
  http.end();
}

void confirmCommand(int id) {
  HTTPClient http;
  http.begin(serverUrlConfirm);
  http.addHeader("Content-Type", "application/json");

  DynamicJsonDocument doc(256);
  doc["id"] = id;
  String requestBody;
  serializeJson(doc, requestBody);

  int httpResponseCode = http.POST(requestBody);
  if (httpResponseCode > 0) {
    Serial.println("Командата е потвърдена и изчистена успешно от опашката.");
  } else {
    Serial.println("Грешка при изпращане на потвърждението.");
  }
  http.end();
}