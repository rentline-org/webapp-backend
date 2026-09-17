<?php

declare(strict_types=1);

return [
    'organization_roles.owner' => 'Proprietário',
    'organization_roles.admin' => 'Administrador',
    'organization_roles.manager' => 'Gestor',
    'organization_roles.agent' => 'Corretor',
    'organization_roles.tenant' => 'Inquilino',
    'invitation.not_found' => 'Este convite não foi encontrado.',
    'invitation.unavailable' => 'Este convite expirou, foi revogado ou já foi aceito.',
    'invitation.already_member' => 'Esta pessoa já é membro ativo da organização.',
    'invitation.tenant_contact_email_required' => 'O contato do inquilino deve ter um e-mail antes do envio do convite.',
    'invitation.contact_email_mismatch' => 'O e-mail do convite deve ser igual ao e-mail do contato do inquilino.',
    'invitation.email_mismatch' => 'Este convite pertence a outro endereço de e-mail.',
    'invitation.name_required' => 'Informe um nome para criar sua conta.',
    'invitation.password_required' => 'Informe uma senha para criar sua conta.',
    'invitation.cannot_resend' => 'Convites aceitos ou revogados não podem ser reenviados.',
    'invitation.cannot_revoke' => 'Convites aceitos não podem ser revogados.',
    'invitation.email.subject' => 'Convite para participar de :organization',
    'invitation.email.greeting' => 'Você recebeu um convite',
    'invitation.email.introduction' => ':inviter convidou você para participar de :organization como :role.',
    'invitation.email.action' => 'Aceitar convite',
    'invitation.email.expiry' => 'Este convite de uso único expira em sete dias.',
    'membership.owner_protected' => 'O proprietário da organização não pode ser alterado nem removido.',
    'membership.tenant_contact_required' => 'Um membro inquilino deve estar vinculado a um contato desta organização.',
    'operations_digest.subject' => ':organization precisa da sua atenção',
    'operations_digest.greeting' => 'Resumo diário de operações',
    'operations_digest.introduction' => 'Há :count item(ns) em aberto relacionado(s) a locações, documentos, assinaturas ou vistorias.',
    'operations_digest.action' => 'Revisar pendências',
    'operations_digest.footer' => 'Este resumo é gerado uma vez por dia no fuso horário da organização.',
    'operations_digest.notification_title' => 'Operações imobiliárias precisam de atenção',
];
