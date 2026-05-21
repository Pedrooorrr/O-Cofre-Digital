# O Cofre Digital — API REST em PHP

API REST minimalista para guardar anotações e senhas secretas com criptografia AES-256.

---

## Requisitos

- PHP 8.1+ (com extensões: `pdo_sqlite` ou `pdo_mysql`, `openssl`)
- Servidor web com mod_rewrite (Apache) **ou** use o servidor embutido do PHP

---

## Instalação

```bash
# 1. Clone ou extraia o projeto
cd cofre-digital

# 2. Copie e ajuste o .env (a chave APP_KEY deve ser única!)
# Edite o arquivo .env e troque APP_KEY por uma string aleatória

# 3. Crie a pasta do banco (SQLite)
mkdir -p database && chmod 775 database

# 4. Inicie o servidor de desenvolvimento
php -S localhost:8080 -t public/
```

O banco de dados SQLite é criado automaticamente em `database/cofre.sqlite` na primeira requisição.

---

## Configuração

Edite o arquivo `.env` na raiz do projeto:

| Variável         | Padrão                    | Descrição                      |
|------------------|---------------------------|--------------------------------|
| `APP_KEY`        | *(troque!)*               | Chave de criptografia AES-256  |
| `DB_DRIVER`      | `sqlite`                  | `sqlite`, `mysql` ou `pgsql`   |
| `DB_FILE`        | `cofre.sqlite`            | Nome do arquivo SQLite         |
| `TOKEN_TTL_HOURS`| `24`                      | Validade do token em horas     |
| `TIMEZONE`       | `America/Sao_Paulo`       | Fuso horário da aplicação      |

---

## Endpoints

### Autenticação

#### `POST /auth/register` — Criar conta
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "password": "minhasenha123"
}
```

#### `POST /auth/login` — Fazer login
```json
{
  "email": "joao@email.com",
  "password": "minhasenha123"
}
```
Retorna um `token` Bearer para usar nas próximas requisições.

---

### Segredos (requer `Authorization: Bearer {token}`)

#### `GET /secrets` — Listar todos os segredos
> Retorna título, tipo e metadados. **O conteúdo não é retornado aqui.**

#### `POST /secrets` — Criar segredo
```json
{
  "title": "Senha do Wi-Fi",
  "content": "minha_senha_secreta",
  "type": "password",
  "is_favorite": true
}
```
- `type`: `note` (anotação) ou `password` (senha)

#### `GET /secrets/{id}` — Revelar segredo
> Retorna o conteúdo **descriptografado**.

#### `PUT /secrets/{id}` — Atualizar segredo
```json
{
  "title": "Novo título",
  "content": "novo conteúdo",
  "is_favorite": false
}
```
> Todos os campos são opcionais.

#### `DELETE /secrets/{id}` — Remover segredo

---

## Exemplo com cURL

```bash
# Registrar
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"João","email":"joao@email.com","password":"123456"}'

# Login (guarde o token retornado)
TOKEN=$(curl -s -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"joao@email.com","password":"123456"}' \
  | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

# Criar segredo
curl -X POST http://localhost:8080/secrets \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Senha GitHub","content":"minha_senha_gh","type":"password"}'

# Listar segredos
curl http://localhost:8080/secrets \
  -H "Authorization: Bearer $TOKEN"

# Revelar segredo (substitua 1 pelo ID)
curl http://localhost:8080/secrets/1 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Estrutura do Projeto

```
cofre-digital/
├── public/
│   ├── index.php        # Entry point (aponte seu servidor aqui)
│   └── .htaccess        # Rewrite rules (Apache)
├── src/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   └── SecretController.php
│   ├── Helpers/
│   │   ├── Crypto.php   # Criptografia AES-256-CBC + HMAC
│   │   ├── Database.php # PDO + migrations automáticas
│   │   ├── Request.php  # Parsing + validação
│   │   ├── Response.php # Respostas JSON padronizadas
│   │   └── Router.php   # Roteador minimalista
│   └── Middleware/
│       └── AuthMiddleware.php
├── config/
│   └── bootstrap.php    # Autoloader + env + error handler
├── database/            # SQLite fica aqui (criado automaticamente)
├── .env                 # Configurações (não versionar em produção!)
└── README.md
```

---

## Segurança

- ✅ Senhas com **bcrypt** (custo 12)
- ✅ Conteúdo dos segredos com **AES-256-CBC + HMAC-SHA256**
- ✅ Tokens de 64 caracteres gerados com `random_bytes`
- ✅ Tokens expiram automaticamente
- ✅ Cada usuário acessa apenas seus próprios segredos
- ✅ Conteúdo nunca exposto no endpoint de listagem
