import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/brand/brand.dart';
import '../../core/network/api_exception.dart';
import '../../core/state/session_controller.dart';
import '../../core/theme/app_theme.dart';
import 'auth_scaffold.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  late final TapGestureRecognizer _termsTap = TapGestureRecognizer()..onTap = () => context.push('/pagina/termos-de-uso');
  late final TapGestureRecognizer _privacyTap = TapGestureRecognizer()..onTap = () => context.push('/pagina/politica-de-privacidade');
  bool _terms = false;
  bool _marketing = false;
  bool _obscure = true;
  bool _loading = false;
  Map<String, String> _fieldErrors = {};
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _termsTap.dispose();
    _privacyTap.dispose();
    super.dispose();
  }

  static const _termsMessage = 'Aceite os Termos de uso e a Política de privacidade para continuar.';

  Future<void> _submit() async {
    setState(() => _fieldErrors = {});
    if (!_form.currentState!.validate()) return;
    if (!_terms) {
      setState(() => _error = _termsMessage);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref.read(sessionProvider.notifier).register(name: _name.text, email: _email.text, password: _password.text, marketing: _marketing);
      if (mounted && context.canPop()) context.pop();
    } on ApiException catch (e) {
      setState(() {
        _fieldErrors = e.fieldErrors;
        _error = e.fieldErrors.isEmpty ? e.message : null;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final link = TextStyle(
      color: context.sancta.primary,
      fontWeight: FontWeight.w600,
      decoration: TextDecoration.underline,
      decorationColor: context.sancta.primary,
    );

    return AuthScaffold(
      title: 'Crie sua conta',
      subtitle: 'É grátis. Guarde seu progresso, favoritos e anotações.',
      scene: SkyScene.entardecer,
      children: [
        Form(
          key: _form,
          child: AutofillGroup(
            child: Column(
              children: [
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  autofillHints: const [AutofillHints.name],
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: 'Nome', prefixIcon: const Icon(Icons.person_outline_rounded), errorText: _fieldErrors['name']),
                  validator: (v) => (v ?? '').trim().length < 2 ? 'Informe seu nome.' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.email],
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: 'E-mail', prefixIcon: const Icon(Icons.mail_outline_rounded), errorText: _fieldErrors['email']),
                  validator: validateEmail,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _password,
                  obscureText: _obscure,
                  autofillHints: const [AutofillHints.newPassword],
                  decoration: InputDecoration(
                    labelText: 'Senha',
                    helperText: 'Mínimo de 8 caracteres, com letras e números',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    errorText: _fieldErrors['password'],
                    suffixIcon: IconButton(
                      tooltip: _obscure ? 'Mostrar senha' : 'Ocultar senha',
                      icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: validatePassword,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        CheckboxListTile(
          value: _terms,
          onChanged: (v) => setState(() {
            _terms = v ?? false;
            // A mensagem de "aceite os termos" some assim que a caixa é marcada.
            if (_terms && _error == _termsMessage) _error = null;
          }),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text.rich(
            TextSpan(
              style: context.text.bodyMedium?.copyWith(color: context.sancta.text),
              children: [
                const TextSpan(text: 'Li e aceito os '),
                TextSpan(text: 'Termos de uso', style: link, recognizer: _termsTap),
                const TextSpan(text: ' e a '),
                TextSpan(text: 'Política de privacidade', style: link, recognizer: _privacyTap),
                const TextSpan(text: '.'),
              ],
            ),
          ),
        ),
        CheckboxListTile(
          value: _marketing,
          onChanged: (v) => setState(() => _marketing = v ?? false),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text('Quero receber novidades, novenas e conteúdos especiais.', style: context.text.bodyMedium?.copyWith(color: context.sancta.text)),
        ),
        const SizedBox(height: 8),
        if (_error != null) FormError(_error!),
        FilledButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Criar conta'),
        ),
        const SizedBox(height: 16),
        Center(
          child: TextButton(onPressed: () => context.pushReplacement('/entrar'), child: const Text('Já tenho conta')),
        ),
      ],
    );
  }
}
