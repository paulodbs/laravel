#!/bin/bash

# setup.sh - Instala dependencias do script Node.js
# Uso: bash setup.sh

set -e

# URL fixa do arquivo .zip para download
ZIP_URL="https://exemplo.com/arquivo.zip"

echo "[+] Verificando Node.js..."

if ! command -v node &> /dev/null; then
    echo "[!] Node.js nao encontrado. Instalando..."
    
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu
        apt-get update
        apt-get install -y nodejs npm curl unzip
    elif command -v yum &> /dev/null; then
        # RHEL/CentOS/Fedora
        yum install -y nodejs npm curl unzip
    elif command -v dnf &> /dev/null; then
        dnf install -y nodejs npm curl unzip
    elif command -v pacman &> /dev/null; then
        # Arch
        pacman -Sy --noconfirm nodejs npm curl unzip
    elif command -v apk &> /dev/null; then
        # Alpine
        apk add --no-cache nodejs npm curl unzip
    elif command -v brew &> /dev/null; then
        # macOS
        brew install node curl unzip
    else
        echo "[-] Gerenciador de pacotes nao suportado. Instale o Node.js manualmente."
        exit 1
    fi
fi

NODE_VERSION=$(node -v | sed 's/v//')
echo "[+] Node.js encontrado: $NODE_VERSION"

if ! command -v npm &> /dev/null; then
    echo "[-] npm nao encontrado. Instale o npm manualmente."
    exit 1
fi

echo "[+] Verificando Go..."

if ! command -v go &> /dev/null; then
    echo "[!] Go nao encontrado. Instalando..."

    if command -v apt-get &> /dev/null; then
        apt-get update
        apt-get install -y golang-go
    elif command -v yum &> /dev/null; then
        yum install -y golang
    elif command -v dnf &> /dev/null; then
        dnf install -y golang
    elif command -v pacman &> /dev/null; then
        pacman -Sy --noconfirm go
    elif command -v apk &> /dev/null; then
        apk add --no-cache go
    elif command -v brew &> /dev/null; then
        brew install go
    else
        echo "[-] Gerenciador de pacotes nao suportado. Instale o Go manualmente."
        exit 1
    fi
fi

GO_VERSION=$(go version)
echo "[+] Go encontrado: $GO_VERSION"

echo "[+] Verificando package.json..."

if [ ! -f "package.json" ]; then
    echo "[+] Criando package.json..."
    cat > package.json <<EOF
{
  "name": "bot",
  "version": "1.0.0",
  "description": "",
  "main": "main.js",
  "scripts": {
    "start": "node main.js"
  },
  "dependencies": {}
}
EOF
fi

echo "[+] Instalando dependencias npm..."

npm install socks hpack colors node-fetch

echo "[+] Baixando arquivo ZIP: $ZIP_URL"

if ! command -v curl &> /dev/null && ! command -v wget &> /dev/null; then
    echo "[-] curl ou wget nao encontrado. Instale um deles para baixar arquivos."
    exit 1
fi

if ! command -v unzip &> /dev/null; then
    echo "[-] unzip nao encontrado. Instale o unzip para extrair arquivos."
    exit 1
fi

ZIP_FILE="download.zip"

if command -v curl &> /dev/null; then
    curl -L -o "$ZIP_FILE" "$ZIP_URL"
else
    wget -O "$ZIP_FILE" "$ZIP_URL"
fi

echo "[+] Extraindo $ZIP_FILE..."
unzip -o "$ZIP_FILE"

echo "[+] Removendo $ZIP_FILE..."
rm -f "$ZIP_FILE"

echo "[+] Arquivo ZIP baixado e extraido com sucesso."

echo "[+] Instalacao concluida."
echo "[+] Execute com: node main.js"

if ! command -v nohup &> /dev/null; then
    echo "[!] nohup nao encontrado. Instalando..."

    if command -v apt-get &> /dev/null; then
        apt-get update
        apt-get install -y coreutils
    elif command -v yum &> /dev/null; then
        yum install -y coreutils
    elif command -v dnf &> /dev/null; then
        dnf install -y coreutils
    elif command -v pacman &> /dev/null; then
        pacman -Sy --noconfirm coreutils
    elif command -v apk &> /dev/null; then
        apk add --no-cache coreutils
    elif command -v brew &> /dev/null; then
        brew install coreutils
    else
        echo "[-] Gerenciador de pacotes nao suportado. Instale o nohup/coreutils manualmente."
        exit 1
    fi
fi

if [ -f "bot.go" ]; then
    echo "[+] Executando bot.go em segundo plano..."
    nohup go run bot.go > bot.log 2>&1 &
    echo "[+] bot.go iniciado em segundo plano (PID: $!)"
else
    echo "[!] bot.go nao encontrado. Pulando execucao."
fi
