import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Pentest fase 3 — SEC-18: o build de release caía na assinatura de DEPURAÇÃO quando faltava
/// `android/key.properties`, gerando um pacote não publicável sem nenhum aviso. Agora a tarefa de
/// release falha, a menos que `SANCTA_ALLOW_DEBUG_SIGNING=true` libere isso de propósito (teste local).
/// Verificação estática do script do Gradle (os testes não rodam o Gradle).
void main() {
  final script = File('android/app/build.gradle.kts').readAsStringSync();
  final code = script.split('\n').where((line) => !line.trimLeft().startsWith('//')).join('\n');

  test('release não cai na chave de depuração sem liberação explícita', () {
    // O padrão antigo: "if (key.properties existe) release else debug".
    expect(RegExp(r'else\s+signingConfigs\.getByName\("debug"\)').hasMatch(code), isFalse);
    expect(code, contains('allowDebugSigning -> signingConfigs.getByName("debug")'));
    expect(code, contains('else -> null'));
  });

  test('a liberação só vem de SANCTA_ALLOW_DEBUG_SIGNING=true (ambiente ou -P)', () {
    expect(code, contains('System.getenv("SANCTA_ALLOW_DEBUG_SIGNING")'));
    expect(code, contains('findProperty("SANCTA_ALLOW_DEBUG_SIGNING")'));
    expect(code, contains('.equals("true", ignoreCase = true)'));
  });

  test('tarefas de release falham sem key.properties', () {
    expect(code, contains('it.name.endsWith("ReleaseBuild")'));
    expect(RegExp(r'if \(!releaseHasKeystore && !releaseAllowsDebugSigning\)\s*\{\s*throw GradleException\(').hasMatch(code), isTrue);
  });
}
