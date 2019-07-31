# Fui exposto? (Telegram Bot)
Bot para Telegram que checa se determinado e-mail está exposto em algum vazamento de dados, via https://haveibeenpwned.com

## Uso

* Altere os IDs no arquivo ***config.php***
* Execute o arquivo ***install.php*** para baixar as dependências (phgram e ExeQue/HIBP)
* Configure o webhook. Aponte a url para ***run.php*** passando a token como parâmetro GET (ou crie um arquivo chamado _token e cole a token lá). Exemplo: `https://domain.com/secretpath/fuiexposto/run.php?token=621879570:AAE3xkPeJByM71ZpHzu4NGYtcOVkQ2zGqh8`

* O arquivo ***check_domains.php*** faz a checagem dos domínios cadastrados com as breaches do Have I Been Pwned. De preferência, configure um cron job para ele.

## Agradecimentos
- [Pedro Pamn](github.com/pedropamn), criador e desenvolvedor do bot original.

## Licença

This project is licensed under the GNU General Public License