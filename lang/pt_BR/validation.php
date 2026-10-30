<?php

return [
    'required' => 'O campo :attribute é obrigatório.', 'string' => 'O campo :attribute deve conter texto.',
    'email' => 'Informe um e-mail válido em :attribute.', 'unique' => 'Este :attribute já está cadastrado.',
    'confirmed' => 'A confirmação de :attribute não corresponde.',
    'boolean' => 'O campo :attribute deve indicar sim ou não.', 'array' => 'O campo :attribute deve ser uma lista.',
    'enum' => 'O valor de :attribute não é permitido.', 'distinct' => 'O campo :attribute contém valores repetidos.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'min' => ['string' => 'O campo :attribute deve ter ao menos :min caracteres.', 'array' => 'Selecione ao menos :min item em :attribute.'],
    'max' => ['string' => 'O campo :attribute aceita no máximo :max caracteres.'],
    'password' => ['mixed' => 'A senha deve conter letras maiúsculas e minúsculas.', 'numbers' => 'A senha deve conter ao menos um número.'],
    'attributes' => ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'roles' => 'perfis',
        'roles.*' => 'perfil', 'reason' => 'justificativa', 'active' => 'estado da conta', 'code' => 'código'],
];
