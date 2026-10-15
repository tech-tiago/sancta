import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/api_exception.dart';
import '../../core/providers.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/location.dart';
import '../../core/widgets/common.dart';

/// Sugestão de nova igreja ou correção (vai para a fila de moderação do painel).
class SuggestChurchScreen extends ConsumerStatefulWidget {
  const SuggestChurchScreen({super.key, this.churchId, this.churchName});

  final String? churchId;
  final String? churchName;

  bool get isCorrection => churchId != null;

  @override
  ConsumerState<SuggestChurchScreen> createState() => _SuggestChurchScreenState();
}

class _SuggestChurchScreenState extends ConsumerState<SuggestChurchScreen> {
  final _form = GlobalKey<FormState>();
  final _fields = {
    for (final k in ['name', 'address', 'neighborhood', 'city', 'state', 'phone', 'instagram', 'mass_times', 'message']) k: TextEditingController(),
  };
  double? _lat;
  double? _lng;
  bool _locating = false;
  bool _sending = false;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    for (final c in _fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _useLocation() async {
    setState(() => _locating = true);
    try {
      final pos = await currentPosition();
      if (!mounted) return;
      setState(() {
        _lat = pos.latitude;
        _lng = pos.longitude;
      });
      showMessage(context, 'Localização adicionada. Envie estando na porta da igreja para maior precisão.');
    } on LocationFailure catch (e) {
      if (mounted) showMessage(context, e.message);
    } catch (_) {
      if (mounted) showMessage(context, 'Não foi possível obter a localização.');
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _sending = true;
      _errors = {};
    });
    try {
      final data = <String, dynamic>{
        if (widget.churchId != null) 'church_uuid': widget.churchId,
        for (final e in _fields.entries)
          if (e.value.text.trim().isNotEmpty) e.key: e.key == 'state' ? e.value.text.trim().toUpperCase() : e.value.text.trim(),
        if (_lat != null) 'latitude': _lat,
        if (_lng != null) 'longitude': _lng,
      };
      final result = await ref.read(apiClientProvider).post('/church-submissions', data: data);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (d) => AlertDialog(
          icon: const Icon(Icons.volunteer_activism_rounded),
          title: const Text('Obrigado!'),
          content: Text('${(result as Map)['message']}'),
          actions: [TextButton(onPressed: () => Navigator.pop(d), child: const Text('Amém'))],
        ),
      );
      if (mounted) context.pop();
    } on ApiException catch (e) {
      setState(() => _errors = e.fieldErrors);
      if (e.fieldErrors.isEmpty && mounted) showMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Widget _field(String key, String label, {bool required = false, int lines = 1, TextInputType? type, int? max}) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: _fields[key],
      maxLines: lines,
      maxLength: max,
      keyboardType: type,
      textCapitalization: key == 'state' ? TextCapitalization.characters : TextCapitalization.sentences,
      decoration: InputDecoration(labelText: required ? '$label *' : label, errorText: _errors[key], counterText: ''),
      validator: required ? (v) => (v ?? '').trim().isEmpty ? 'Campo obrigatório.' : null : null,
    ),
  );

  @override
  Widget build(BuildContext context) {
    final correction = widget.isCorrection;
    return Scaffold(
      appBar: AppBar(title: Text(correction ? 'Corrigir informações' : 'Sugerir igreja')),
      body: Form(
        key: _form,
        child: ListView(
          padding: EdgeInsets.fromLTRB(20, 4, 20, 32 + MediaQuery.paddingOf(context).bottom),
          children: [
            Text(
              correction
                  ? 'O que mudou em ${widget.churchName ?? 'esta igreja'}? Preencha só o que precisa ser corrigido.'
                  : 'Sua sugestão será revisada pela equipe antes de aparecer no mapa.',
              style: context.text.bodyLarge,
            ),
            const SizedBox(height: 18),
            if (correction) _field('message', 'Descreva a correção', required: true, lines: 4, max: 2000),
            _field('name', 'Nome da igreja', required: !correction, max: 191),
            _field('address', 'Endereço (rua e número)', required: !correction, max: 255),
            _field('neighborhood', 'Bairro', max: 120),
            Row(
              children: [
                Expanded(flex: 3, child: _field('city', 'Cidade', required: !correction, max: 120)),
                const SizedBox(width: 12),
                Expanded(child: _field('state', 'UF', required: !correction, max: 2)),
              ],
            ),
            _field('mass_times', 'Horários de missa', lines: 3, max: 1000),
            _field('phone', 'Telefone', type: TextInputType.phone, max: 30),
            _field('instagram', 'Instagram', max: 80),
            if (!correction) _field('message', 'Observações', lines: 3, max: 2000),
            OutlinedButton.icon(
              onPressed: _locating ? null : _useLocation,
              icon: _locating
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : Icon(_lat != null ? Icons.check_circle_rounded : Icons.my_location_rounded),
              label: Text(
                _locating
                    ? 'Obtendo localização…'
                    : _lat != null
                    ? 'Localização adicionada'
                    : 'Usar minha localização atual',
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _sending ? null : _submit,
              child: _sending ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Enviar para revisão'),
            ),
          ],
        ),
      ),
    );
  }
}
