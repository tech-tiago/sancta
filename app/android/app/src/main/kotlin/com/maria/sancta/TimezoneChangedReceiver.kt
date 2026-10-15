package com.maria.sancta

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import org.json.JSONArray
import java.time.LocalDateTime
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.util.TimeZone

/**
 * Mudança de fuso/horário do aparelho (broadcast do sistema; receiver NÃO exportado):
 *  - redesenha o widget "Oração do dia" (período e data podem ter mudado);
 *  - lembretes de oração: mantém o horário LOCAL escolhido (ex.: 07:00 em qualquer fuso). Atualiza o
 *    fuso dos lembretes no cache do flutter_local_notifications e pede ao plugin que reagende pelo
 *    mesmo caminho usado no boot. Se algo falhar, o app reagenda ao ser aberto (ReminderController).
 */
class TimezoneChangedReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_TIMEZONE_CHANGED -> {
                OracaoDoDiaWidgetProvider.refreshAll(context)
                moveRemindersToCurrentZone(context)
            }
            Intent.ACTION_TIME_CHANGED -> OracaoDoDiaWidgetProvider.refreshAll(context)
        }
    }

    private fun moveRemindersToCurrentZone(context: Context) {
        try {
            val prefs = context.getSharedPreferences(PLUGIN_CACHE, Context.MODE_PRIVATE)
            val raw = prefs.getString(PLUGIN_CACHE, null) ?: return
            val items = JSONArray(raw)
            val zone = ZoneId.of(TimeZone.getDefault().id)
            val now = ZonedDateTime.now(zone)
            var changed = false

            for (i in 0 until items.length()) {
                val item = items.optJSONObject(i) ?: continue
                if (item.optInt("id") !in REMINDER_IDS) continue
                val time = LocalDateTime.parse(item.getString("scheduledDateTime")).toLocalTime()
                var next = now.with(time)
                if (!next.isAfter(now)) next = next.plusDays(1)
                item.put("timeZoneName", zone.id)
                item.put("scheduledDateTime", next.toLocalDateTime().format(DateTimeFormatter.ISO_LOCAL_DATE_TIME))
                changed = true
            }
            if (!changed) return

            prefs.edit().putString(PLUGIN_CACHE, items.toString()).commit()
            // Broadcast EXPLÍCITO para o receiver (não exportado) do próprio app; ação aceita pelo plugin.
            context.sendBroadcast(Intent(RESCHEDULE_ACTION).setClassName(context, PLUGIN_BOOT_RECEIVER))
        } catch (_: Exception) {
            // Formato do cache mudou ou dado inválido: o app reagenda na próxima abertura.
        }
    }

    private companion object {
        /** IDs dos lembretes diários (ReminderSchedule no Dart). */
        val REMINDER_IDS = setOf(7101, 7102)
        const val PLUGIN_CACHE = "scheduled_notifications"
        const val PLUGIN_BOOT_RECEIVER = "com.dexterous.flutterlocalnotifications.ScheduledNotificationBootReceiver"
        const val RESCHEDULE_ACTION = "android.intent.action.QUICKBOOT_POWERON"
    }
}
