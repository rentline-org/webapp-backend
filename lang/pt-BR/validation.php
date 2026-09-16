<?php

declare(strict_types=1);

return [
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não estiver presente.',
    'email' => 'O campo :attribute deve conter um endereço de e-mail válido.',
    'string' => 'O campo :attribute deve ser um texto.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'exists' => 'O valor selecionado para :attribute é inválido.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'max' => [
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'confirmed' => 'A confirmação do campo :attribute não corresponde.',
    'attributes' => [
        'email' => 'e-mail',
        'role' => 'função',
        'locale' => 'idioma',
        'contact_id' => 'contato',
        'name' => 'nome',
        'password' => 'senha',
    ],
];
