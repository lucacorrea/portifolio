CREATE DATABASE floricultura

CREATE TABLE clientes(
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(80),
    sobrenome VARCHAR(80),
    email VARCHAR(80),
    cidade VARCHAR(50),
    endereco VARCHAR(120),
    cep VARCHAR(20),
    numero VARCHAR(20),
    comentário VARCHAR(120)
    );

CREATE TABLE produtos(
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100),
    preco FLOAT,
    categoria VARCHAR(40)
    descricao VARCHAR(200)
    quantidade INT NOT NULL,
    imagem VARCHAR(255),
    );