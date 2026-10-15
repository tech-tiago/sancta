package com.maria.sancta

import android.app.AlarmManager
import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.util.SizeF
import android.widget.RemoteViews
import es.antonborri.home_widget.HomeWidgetLaunchIntent
import es.antonborri.home_widget.HomeWidgetPlugin
import es.antonborri.home_widget.HomeWidgetProvider
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

/**
 * Widget "Oração do dia" (mockup aprovado: cartão Azul Manto, céu em arco, data, título, duração e play).
 *
 * O app grava (home_widget, chave `sancta_widget`) um JSON com a oração de cada período — manhã,
 * tarde e noite — e o widget escolhe o do horário atual, mesmo sem o app abrir. Sem dados: conteúdo
 * padrão do período, abrindo a tela Hoje. A data é sempre a de hoje (pt-BR). Nada de dados pessoais.
 *
 * Toque no cartão ou no play: abre o app com o deep link `sancta://` (validado aqui e de novo no
 * Flutter) via `HomeWidgetLaunchIntent` (PendingIntent imutável).
 */
class OracaoDoDiaWidgetProvider : HomeWidgetProvider() {

    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray,
        widgetData: SharedPreferences,
    ) {
        for (id in appWidgetIds) {
            appWidgetManager.updateAppWidget(id, buildViews(context, appWidgetManager.getAppWidgetOptions(id), widgetData))
        }
        scheduleNextRefresh(context)
    }

    override fun onAppWidgetOptionsChanged(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int, newOptions: Bundle) {
        super.onAppWidgetOptionsChanged(context, appWidgetManager, appWidgetId, newOptions)
        appWidgetManager.updateAppWidget(appWidgetId, buildViews(context, newOptions, HomeWidgetPlugin.getData(context)))
    }

    override fun onDisabled(context: Context) {
        super.onDisabled(context)
        context.getSystemService(AlarmManager::class.java)?.cancel(refreshIntent(context, IntArray(0)))
    }

    /** Conteúdo exibido num período. */
    data class Entry(val title: String, val subtitle: String, val link: String)

    /** Períodos (iguais a `WidgetPeriod` no Dart): manhã 05h–11h59 · tarde 12h–17h59 · noite 18h–04h59. */
    enum class Period(val key: String, val defaultTitle: String) {
        MANHA("manha", "Oração da manhã"),
        TARDE("tarde", "Oração da tarde"),
        NOITE("noite", "Oração da noite"),
    }

    companion object {
        private const val DATA_KEY = "sancta_widget"
        private const val DEFAULT_SUBTITLE = "Rezar agora"
        private const val DEFAULT_LINK = "sancta://hoje"
        private const val REQUEST_REFRESH = 7300

        /** Só deep links internos simples (`sancta://secao` ou `sancta://secao/slug`). */
        private val SAFE_LINK = Regex("^sancta://[a-z-]{1,30}(/[a-z0-9][a-z0-9-]{0,119})?$")

        /** Larguras (dp) dos layouts responsivos. */
        private const val COMPACT_MAX_WIDTH = 220

        private val PT_BR = Locale("pt", "BR")

        fun periodOf(hour: Int): Period = when (hour) {
            in 5..11 -> Period.MANHA
            in 12..17 -> Period.TARDE
            else -> Period.NOITE
        }

        /** Céu da marca do horário (mesma regra de `SkyArt.sceneFor`). */
        fun skyOf(hour: Int): Int = when (hour) {
            in 5..11 -> R.drawable.widget_sky_aurora
            in 12..15 -> R.drawable.widget_sky_luz
            in 16..18 -> R.drawable.widget_sky_entardecer
            else -> R.drawable.widget_sky_noite
        }

        fun isSafeLink(link: String?): Boolean = link != null && SAFE_LINK.matches(link)

        /** "Domingo, 4 de outubro". */
        fun dateLabel(now: Calendar): String =
            SimpleDateFormat("EEEE, d 'de' MMMM", PT_BR).format(now.time).replaceFirstChar { it.titlecase(PT_BR) }

        /** Lê o conteúdo do período atual; qualquer problema → conteúdo padrão. */
        fun entryFor(widgetData: SharedPreferences, now: Calendar): Entry {
            val period = periodOf(now.get(Calendar.HOUR_OF_DAY))
            val fallback = Entry(period.defaultTitle, DEFAULT_SUBTITLE, DEFAULT_LINK)
            val raw = widgetData.getString(DATA_KEY, null) ?: return fallback
            return try {
                val json = JSONObject(raw)
                val item = json.optJSONObject("periods")?.optJSONObject(period.key) ?: return fallback
                val link = item.optString("link")
                if (!isSafeLink(link)) return fallback
                val today = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(now.time)
                val stale = json.optString("date") != today
                val title = item.optString("title").trim().take(80).ifEmpty { period.defaultTitle }
                val subtitle = if (stale && item.optBoolean("daily")) DEFAULT_SUBTITLE else item.optString("subtitle").trim().take(60).ifEmpty { DEFAULT_SUBTITLE }
                Entry(title, subtitle, link)
            } catch (_: Exception) {
                fallback
            }
        }

        fun buildViews(context: Context, options: Bundle?, widgetData: SharedPreferences): RemoteViews {
            val now = Calendar.getInstance()
            val entry = entryFor(widgetData, now)
            val wide = fill(context, RemoteViews(context.packageName, R.layout.widget_oracao_do_dia), entry, now, compact = false)
            val compact = fill(context, RemoteViews(context.packageName, R.layout.widget_oracao_do_dia_compact), entry, now, compact = true)

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                // Android 12+: o sistema escolhe o layout pelo tamanho real do widget.
                return RemoteViews(mapOf(SizeF(110f, 110f) to compact, SizeF(COMPACT_MAX_WIDTH.toFloat(), 110f) to wide))
            }
            val minWidth = options?.getInt(AppWidgetManager.OPTION_APPWIDGET_MIN_WIDTH, 0) ?: 0
            return if (minWidth in 1 until COMPACT_MAX_WIDTH) compact else wide
        }

        private fun fill(context: Context, views: RemoteViews, entry: Entry, now: Calendar, compact: Boolean): RemoteViews {
            views.setTextViewText(R.id.widget_date, dateLabel(now))
            views.setTextViewText(R.id.widget_title, entry.title)
            if (!compact) views.setTextViewText(R.id.widget_subtitle, entry.subtitle)
            views.setImageViewResource(R.id.widget_sky, skyOf(now.get(Calendar.HOUR_OF_DAY)))
            views.setContentDescription(R.id.widget_play, "Rezar agora: ${entry.title}")

            val open = HomeWidgetLaunchIntent.getActivity(context, MainActivity::class.java, Uri.parse(entry.link))
            views.setOnClickPendingIntent(R.id.widget_root, open)
            views.setOnClickPendingIntent(R.id.widget_play, open)
            return views
        }

        /** Redesenha todos os widgets (ex.: mudança de fuso/horário). */
        fun refreshAll(context: Context) {
            val ids = AppWidgetManager.getInstance(context).getAppWidgetIds(ComponentName(context, OracaoDoDiaWidgetProvider::class.java))
            if (ids.isNotEmpty()) context.sendBroadcast(updateIntent(context, ids))
        }

        private fun updateIntent(context: Context, ids: IntArray) =
            Intent(context, OracaoDoDiaWidgetProvider::class.java)
                .setAction(AppWidgetManager.ACTION_APPWIDGET_UPDATE)
                .putExtra(AppWidgetManager.EXTRA_APPWIDGET_IDS, ids)

        private fun refreshIntent(context: Context, ids: IntArray): PendingIntent =
            PendingIntent.getBroadcast(context, REQUEST_REFRESH, updateIntent(context, ids), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        /** Próxima troca de céu/período/data: 00h, 05h, 12h, 16h, 18h e 19h. */
        fun nextBoundary(now: Calendar): Long {
            val hours = intArrayOf(0, 5, 12, 16, 18, 19)
            for (day in 0..1) {
                for (hour in hours) {
                    val at = (now.clone() as Calendar).apply {
                        add(Calendar.DAY_OF_YEAR, day)
                        set(Calendar.HOUR_OF_DAY, hour)
                        set(Calendar.MINUTE, 0)
                        set(Calendar.SECOND, 1)
                        set(Calendar.MILLISECOND, 0)
                    }
                    if (at.after(now)) return at.timeInMillis
                }
            }
            return now.timeInMillis + 60 * 60 * 1000L
        }

        /**
         * Alarme INEXATO e sem despertar o aparelho (RTC): roda quando o aparelho estiver em uso,
         * que é quando o widget é visto. Não exige permissão de alarme exato.
         */
        private fun scheduleNextRefresh(context: Context) {
            val ids = AppWidgetManager.getInstance(context).getAppWidgetIds(ComponentName(context, OracaoDoDiaWidgetProvider::class.java))
            if (ids.isEmpty()) return
            val alarms = context.getSystemService(AlarmManager::class.java) ?: return
            alarms.set(AlarmManager.RTC, nextBoundary(Calendar.getInstance()), refreshIntent(context, ids))
        }
    }
}
